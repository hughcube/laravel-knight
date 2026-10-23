<?php

namespace HughCube\Laravel\Knight\Scheduling;

use Carbon\Carbon;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 原生 PHP CLI 零依赖顺序循环调度驱动（稳定兜底驱动）.
 *
 * =========================================================================
 * 【核心设计思路】
 * =========================================================================
 * 1. 零外部扩展依赖：
 *    无需安装 Swoole 或任何 C 扩展，100% 运行在标准的纯 PHP CLI 环境下，
 *    天然兼容所有操作系统（Linux、macOS、Windows）与 Docker 基础镜像。
 *
 * 2. 顺序安全执行（Zero-Concurrency）：
 *    所有到期任务严格按照注册顺序依次同步执行，一个跑完再跑下一个。
 *    彻底物理杜绝多协程/多线程并发读写同一个 PDO Socket 引发的 "Socket already bound" 错误。
 *
 * 3. 彻底消除 CPU 冷启动毛刺：
 *    与 SwooleDriver 一样，通过 TaskRunner 走 ConsoleKernel::call() 内存直驱，
 *    彻底消灭每分钟全量加载编译框架造成的单核 40% CPU 锯齿，耗时仅几毫秒。
 *
 * 4. 单调时钟防 NTP 回拨与秒级任务支持：
 *    同样内置单调时间戳比对算法（免疫 NTP 时钟后退回拨），并原生支持 Laravel 官方秒级重复调度。
 *
 * =========================================================================
 * 【适用场景】
 * =========================================================================
 * - 生产环境未安装 Swoole 扩展时的全自动降级；
 * - 业务代码包含历史遗留的大量原生全局变量、原生单例 PDO，极度敏感于协程并发冲突的场景；
 * - 开发者本地调试或单元测试环境。
 */
class NativeDriver implements DriverInterface
{
    protected OutputInterface $output;
    protected bool $shouldQuit = false;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @inheritDoc
     */
    public function run(Schedule $schedule, TaskRunner $runner, array $options): int
    {
        $interval = max(50, (int) ($options['interval'] ?? 200));
        $maxMemory = (int) ($options['max_memory'] ?? 128);
        $maxRuns = (int) ($options['max_runs'] ?? 0);
        $immediate = (bool) ($options['immediate'] ?? false);
        $whisper = (bool) ($options['whisper'] ?? false);

        $lastExecutedMinuteTimestamp = null;
        $runs = 0;

        while (!$this->isStopped()) {
            $now = Carbon::now();
            $currentMinuteStart = $now->copy()->startOfMinute()->timestamp;

            // 核心单调时钟防漂移与防 NTP 回拨算法：
            // 1. 首轮若未开启 immediate 且在 4~59 秒启动，则等到下个整点触发；
            // 2. 次轮起只要当前分钟时间戳严格大于上一轮（单调递增），无论第 0 还是 1 秒均准时触发；
            // 3. 若发生 NTP 时钟后退回拨，由于时间戳 <= 上一轮时间戳，绝不重复触发！
            $isNewMinute = ($lastExecutedMinuteTimestamp === null
                || $currentMinuteStart > $lastExecutedMinuteTimestamp);
            $isTopBoundary = ($now->second <= 3);

            if ($isNewMinute && ($immediate || $lastExecutedMinuteTimestamp !== null || $isTopBoundary)) {
                $lastExecutedMinuteTimestamp = $currentMinuteStart;
                $startedAt = Carbon::createFromTimestamp($currentMinuteStart);
                $endOfMinute = $startedAt->copy()->endOfMinute();
                $runs++;

                // 1. 每轮新分钟开始：重置上一分钟的单机分布式锁缓存
                $runner->resetScheduleForNewMinute($schedule);

                // 2. 获取当分钟所有到期事件
                $app = $runner->getApplication();
                $events = $schedule->dueEvents($app);
                $paused = $runner->isSchedulePaused();
                $eventsRan = false;

                // 3. 第一阶段：执行整点首批任务（100% 对齐官方 ScheduleRunCommand）
                foreach ($events as $event) {
                    if ($this->isStopped()) {
                        break;
                    }

                    if ($paused && !(method_exists($event, 'runsWhenPaused') && $event->runsWhenPaused())) {
                        $runner->dispatcher()->dispatch(new ScheduledTaskSkipped($event));
                        continue;
                    }

                    if (!$event->filtersPass($app)) {
                        $runner->dispatcher()->dispatch(new ScheduledTaskSkipped($event));
                        continue;
                    }

                    if (!$eventsRan) {
                        $this->output->writeln('');
                    }

                    if ($event->onOneServer) {
                        if ($runner->serverShouldRun($event, $schedule, $startedAt)) {
                            $runner->runSafely($event);
                        }
                    } else {
                        $runner->runSafely($event);
                    }

                    $eventsRan = true;
                }

                // 4. 第二阶段：执行秒级重复任务（直到当前分钟结束，100% 对齐官方 repeatEvents 契约）
                $repeatableEvents = $events->filter(function ($event) {
                    return method_exists($event, 'isRepeatable') && $event->isRepeatable();
                });

                if ($repeatableEvents->isNotEmpty() && !$this->isStopped()) {
                    $hasEnteredMaintenanceMode = false;

                    while (!$this->isStopped() && Carbon::now()->lte($endOfMinute)) {
                        $isInterrupted = method_exists($schedule, 'hasBeenInterruptedSince')
                            && $schedule->hasBeenInterruptedSince($startedAt);
                        if ($isInterrupted) {
                            break;
                        }

                        $paused = $runner->isSchedulePaused();

                        foreach ($repeatableEvents as $event) {
                            if ($this->isStopped()) {
                                break;
                            }

                            if (!$event->shouldRepeatNow()) {
                                continue;
                            }

                            if (Carbon::now()->gt($endOfMinute)) {
                                break 2;
                            }

                            if (method_exists($app, 'isDownForMaintenance')) {
                                $hasEnteredMaintenanceMode = $hasEnteredMaintenanceMode
                                    || $app->isDownForMaintenance();
                            }

                            if ($hasEnteredMaintenanceMode && !$event->runsInMaintenanceMode()) {
                                continue;
                            }

                            $isPausedSkipped = $paused
                                && !(method_exists($event, 'runsWhenPaused') && $event->runsWhenPaused());
                            if ($isPausedSkipped) {
                                $runner->dispatcher()->dispatch(new ScheduledTaskSkipped($event));
                                $runner->touchRepeatEvent($event);
                                continue;
                            }

                            if (!$event->filtersPass($app)) {
                                $runner->dispatcher()->dispatch(new ScheduledTaskSkipped($event));
                                continue;
                            }

                            if (!$eventsRan) {
                                $this->output->writeln('');
                            }

                            if ($event->onOneServer) {
                                if ($runner->serverShouldRun($event, $schedule, $startedAt)) {
                                    $runner->runSafely($event);
                                }
                            } else {
                                $runner->runSafely($event);
                            }

                            $eventsRan = true;
                        }

                        usleep(100 * 1000);
                    }
                }

                // 5. 第三阶段：整分钟轮次结束收尾
                if (!$eventsRan) {
                    if (!$whisper) {
                        $runner->components()->info('No scheduled commands are ready to run.');
                    }
                } else {
                    $this->output->writeln('');
                }

                // 6. 物理连接收尾断开（防常驻长连接闲置超时 Gone Away）
                $runner->purgeAllConnections();

                // 7. 轮数熔断安全检测
                if ($maxRuns > 0 && $runs >= $maxRuns) {
                    $this->output->writeln(sprintf(
                        '  <fg=cyan>Reached max-runs threshold (%d). Scheduling graceful exit...</>',
                        $maxRuns
                    ));
                    $this->shouldQuit = true;
                }

                // 7. 内存超限平滑退出自愈检测
                $memMB = memory_get_usage(true) / 1024 / 1024;
                if ($maxMemory > 0 && $memMB >= $maxMemory) {
                    $this->output->writeln(sprintf(
                        '  <fg=yellow>Memory usage (%.2fMB) exceeded threshold (%dMB). Scheduling graceful exit...</>',
                        $memMB,
                        $maxMemory
                    ));
                    $this->shouldQuit = true;
                }

                if ($this->isStopped()) {
                    break;
                }
            }

            usleep($interval * 1000);
        }

        $this->output->writeln('  <fg=green>Schedule daemon (native) successfully stopped.</>');

        return 0;
    }

    /**
     * 接收外部中断退出信号.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * 当前守护驱动是否已被要求停止.
     *
     * @phpstan-impure
     */
    public function isStopped(): bool
    {
        return $this->shouldQuit;
    }
}
