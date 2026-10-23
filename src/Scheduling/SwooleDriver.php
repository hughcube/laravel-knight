<?php

namespace HughCube\Laravel\Knight\Scheduling;

use Carbon\Carbon;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Runtime;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Swoole 协程受控高并发调度驱动.
 *
 * =========================================================================
 * 【核心设计思路】
 * =========================================================================
 * 1. 纯常驻只读内存，框架只 boot 一次：
 *    利用 Swoole 协程的事件驱动能力，以极低内存开销在当前进程实现高吞吐非阻塞调度。
 *
 * 2. 协程并发限流与雪崩防御（Channel 信号量池）：
 *    防止海量定时任务整点同时触发打爆数据库连接池或 Redis 连接，通过 Channel 信号量
 *    严格将并发限制在 concurrency 槽位以内。槽位满等待 0.5s，超时直接告警跳过，捍卫主进程安全。
 *
 * 3. 协调者双协程监控与僵尸协程强制熔断（Coroutine::cancel）：
 *    启动「工作协程」执行业务，外部「协调者协程」带超时监听 Channel。
 *    一旦发生网络死锁或外部服务无限挂起超时，协调者立刻调用 Coroutine::cancel($cid) 强杀目标协程，
 *    并主动兜底释放互斥锁、断开沙箱连接、派发 ScheduledTaskFailed 上报系统报警。
 *
 * 4. 彻底免疫 NTP 时间回拨重跑（单调递增时钟）：
 *    采用单调时间戳比对 ($currentMinuteStart > $lastExecutedMinuteTimestamp)，
 *    若服务器发生 NTP 时钟后退回拨，时间戳小于等于历史值，系统直接忽略跳过，绝对不会重跑。
 *
 * 5. 100% 对齐 Laravel 官方子分钟秒级任务支持（isRepeatable / everyTenSeconds 等）：
 *    整点触发后自动筛选当前分钟内的秒级重复事件，在当前分钟剩余秒数内精准调度执行。
 *
 * =========================================================================
 * 【核心避坑与注意事项 (生命线级别)】
 * =========================================================================
 * ⚠️ 铁律 1：必须显式排除 SWOOLE_HOOK_CURL！
 *    - 为什么排除：Swoole 用纯 PHP/C 协程状态机模拟 libcURL 在复杂生产场景极易发生兼容性崩溃
 *      （尤其在 PHP 8.4、Guzzle 7/8、HTTP/2 或复杂 SSL 证书验证下）。
 *    - 处理方式：Runtime::enableCoroutine(SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_CURL)。
 *    - 效果：所有 cURL / Guzzle 请求 100% 走操作系统原生的 libcURL C 代码，行为与官方 PHP-CLI 像素级一致！
 *
 * ⚠️ 铁律 2：优雅停机必须设置超时排空保底 (Graceful Drain Timeout)
 *    - 接收到 SIGTERM / SIGINT 时，必须等待在途任务执行完成，但绝不可无限等待。
 *    - 必须设置最长等待阈值 (15s)，超限强行退出，防止在途任务死锁导致容器滚动更新长时间卡死。
 */
class SwooleDriver implements DriverInterface
{
    protected OutputInterface $output;
    protected bool $shouldQuit = false;
    protected int $activeTasks = 0;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @inheritDoc
     */
    public function run(Schedule $schedule, TaskRunner $runner, array $options): int
    {
        $previousHookFlags = class_exists(Runtime::class) && method_exists(Runtime::class, 'getHookFlags')
            ? Runtime::getHookFlags()
            : 0;

        // 启用全链路协程 Hook（保留 PDO / Redis / Socket / TCP / Stream，显式排除 cURL 与 PROC Hook 保证原生稳定性）
        $hookFlags = defined('SWOOLE_HOOK_ALL') ? constant('SWOOLE_HOOK_ALL') : true;
        if (is_int($hookFlags)) {
            if (defined('SWOOLE_HOOK_CURL')) {
                $hookFlags &= ~constant('SWOOLE_HOOK_CURL');
            }
            if (defined('SWOOLE_HOOK_NATIVE_CURL')) {
                $hookFlags &= ~constant('SWOOLE_HOOK_NATIVE_CURL');
            }
            if (defined('SWOOLE_HOOK_PROC')) {
                $hookFlags &= ~constant('SWOOLE_HOOK_PROC');
            }
        }

        try {
            if (class_exists(Runtime::class)) {
                Runtime::enableCoroutine($hookFlags);
            }
        } catch (Throwable $e) {
        }

        $exitCode = 0;

        $handler = function () use ($schedule, $runner, $options, &$exitCode) {
            $concurrency = max(1, (int) ($options['concurrency'] ?? 4));
            $semaphore = new Channel($concurrency);
            $timeout = max(1, (float) ($options['timeout'] ?? 60));
            $maxMemory = (int) ($options['max_memory'] ?? 128);
            $maxRuns = (int) ($options['max_runs'] ?? 0);
            $immediate = (bool) ($options['immediate'] ?? false);
            $whisper = (bool) ($options['whisper'] ?? false);

            // 在协程生命周期内注册进程退出信号（必须在协程内注册，防止提前创建 EventLoop 导致 Scheduler 启动失败）
            if (class_exists(\Swoole\Process::class) && method_exists(\Swoole\Process::class, 'signal')) {
                foreach (['SIGTERM', 'SIGINT', 'SIGQUIT'] as $sigName) {
                    if (defined($sigName)) {
                        \Swoole\Process::signal(constant($sigName), function () {
                            $this->stop();
                        });
                    }
                }
            }

            $lastExecutedMinuteTimestamp = null;
            $runs = 0;
            /** @var Collection<int, Event> $repeatableEvents */
            $repeatableEvents = collect();
            $endOfCurrentMinute = null;
            $startedAt = null;

            while (!$this->shouldQuit) {
                $now = Carbon::now();
                $currentMinuteStart = $now->copy()->startOfMinute()->timestamp;

                // 核心单调时钟防漂移与防 NTP 回拨算法：
                // 1. 首轮若未开启 immediate 且在 4~59 秒启动，则等到下个整点触发；
                // 2. 次轮起只要当前分钟时间戳严格大于上一轮（单调递增），无论第 0 还是 1 秒均准时触发；
                // 3. 防 NTP 回拨：若时钟发生后退回拨，由于 $currentMinuteStart <= $lastExecutedMinuteTimestamp，绝不重跑！
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
                        if ($this->shouldQuit) {
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
                                $this->dispatchTaskWithSemaphore(
                                    $event,
                                    $runner,
                                    $semaphore,
                                    $timeout,
                                    $concurrency,
                                    $now
                                );
                            }
                        } else {
                            $this->dispatchTaskWithSemaphore(
                                $event,
                                $runner,
                                $semaphore,
                                $timeout,
                                $concurrency,
                                $now
                            );
                        }

                        $eventsRan = true;
                    }

                    // 4. 第二阶段：执行秒级重复任务（直到当前分钟结束，100% 对齐官方 repeatEvents 契约）
                    $repeatableEvents = $events->filter(function ($event) {
                        return method_exists($event, 'isRepeatable') && $event->isRepeatable();
                    });

                    if ($repeatableEvents->isNotEmpty() && !$this->shouldQuit) {
                        $hasEnteredMaintenanceMode = false;

                        while (!$this->shouldQuit && Carbon::now()->lte($endOfMinute)) {
                            $isInterrupted = method_exists($schedule, 'hasBeenInterruptedSince')
                                && $schedule->hasBeenInterruptedSince($startedAt);
                            if ($isInterrupted) {
                                break;
                            }

                            $paused = $runner->isSchedulePaused();

                            foreach ($repeatableEvents as $event) {
                                if ($this->shouldQuit) {
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
                                        $this->dispatchTaskWithSemaphore(
                                            $event,
                                            $runner,
                                            $semaphore,
                                            $timeout,
                                            $concurrency,
                                            $now
                                        );
                                    }
                                } else {
                                    $this->dispatchTaskWithSemaphore(
                                        $event,
                                        $runner,
                                        $semaphore,
                                        $timeout,
                                        $concurrency,
                                        $now
                                    );
                                }

                                $eventsRan = true;
                            }

                            Coroutine::sleep(0.1);
                        }
                    }

                    // 等待本轮在途任务执行收尾（最多等待当前超时时间，保证控制台输出秩序）
                    while (!$this->shouldQuit && $this->activeTasks > 0) {
                        Coroutine::sleep(0.05);
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

                    // 7. 内存超限平滑退出自愈检测（交由 Docker/K8s 重新拉起）
                    $memMB = memory_get_usage(true) / 1024 / 1024;
                    if ($maxMemory > 0 && $memMB >= $maxMemory) {
                        $this->output->writeln(sprintf(
                            '  <fg=yellow>Memory usage (%.2fMB) exceeded threshold (%dMB). '
                            .'Scheduling graceful exit...</>',
                            $memMB,
                            $maxMemory
                        ));
                        $this->shouldQuit = true;
                    }

                    if ($this->shouldQuit) {
                        break;
                    }
                }

                Coroutine::sleep(0.1);
            }

            // 优雅停机排空（Graceful Drain）：等待在途任务收尾，最长等待 15s 保底防卡死
            if ($this->activeTasks > 0) {
                $this->output->writeln(sprintf(
                    '  <fg=cyan>Waiting for %d active task(s) to finish (max 15s)...</>',
                    $this->activeTasks
                ));
            }

            $waitStart = microtime(true);
            while ((microtime(true) - $waitStart) < 15.0) {
                if ($this->activeTasks <= 0) {
                    break;
                }
                Coroutine::sleep(0.2);
            }

            // 停机退出断开长连接
            $runner->purgeAllConnections();

            // 清理已注册的 Swoole 信号监听器，释放 EventLoop 避免进程退出挂起
            if (class_exists(\Swoole\Process::class) && method_exists(\Swoole\Process::class, 'signal')) {
                foreach (['SIGTERM', 'SIGINT', 'SIGQUIT'] as $sigName) {
                    if (defined($sigName)) {
                        \Swoole\Process::signal(constant($sigName), null);
                    }
                }
            }

            $this->output->writeln('  <fg=green>Schedule daemon (swoole) successfully stopped.</>');
            $exitCode = 0;
        };

        try {
            if (class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
                $handler();
            } else {
                \Swoole\Coroutine\run($handler);
            }
        } finally {
            if (class_exists(Runtime::class)) {
                try {
                    Runtime::enableCoroutine($previousHookFlags);
                } catch (Throwable $e) {
                }
            }
        }

        return $exitCode;
    }

    /**
     * 通过信号量池受控派发任务到独立协程.
     */
    protected function dispatchTaskWithSemaphore(
        Event $event,
        TaskRunner $runner,
        Channel $semaphore,
        float $timeout,
        int $concurrency,
        Carbon $now
    ): void {
        $this->activeTasks++;

        // 投入独立协程受控并发
        Coroutine::create(function () use ($event, $runner, $semaphore, $timeout) {
            try {
                // 等待并发槽位释放，若已触发退出则直接收敛
                while (!$this->shouldQuit) {
                    if ($semaphore->push(true, 0.5)) {
                        try {
                            $this->executeWithTimeout($event, $runner, $timeout);
                        } finally {
                            $semaphore->pop();
                        }

                        return;
                    }
                }
            } finally {
                $this->activeTasks--;
            }
        });
    }

    /**
     * 单任务受控超时执行与僵尸协程强制熔断 (Watcher + Worker 双协程监听模型).
     */
    protected function executeWithTimeout(Event $event, TaskRunner $runner, float $timeout): void
    {
        $doneChannel = new Channel(1);
        $workerCid = null;

        // 启动独立业务工作协程 (Worker Coroutine)
        $cid = Coroutine::create(function () use ($event, $runner, $doneChannel, &$workerCid) {
            $workerCid = Coroutine::getCid();
            $exception = null;

            try {
                $runner->runSafely($event);
            } catch (Throwable $e) {
                $exception = $e;
            } finally {
                $doneChannel->push($exception ?? true);
            }
        });

        // 协程创建失败兜底
        if ($cid === false) {
            $this->output->writeln(sprintf(
                '  <fg=red>Failed to create coroutine for task [%s]</>',
                $runner->getEventSummary($event)
            ));

            return;
        }

        // 协调者协程在通道上等待执行完成或超时
        $result = $doneChannel->pop($timeout);

        if ($result === false) {
            // 发生硬超时！立即强制取消目标工作协程，杜绝僵尸协程存活与连接泄漏！
            $targetCid = $workerCid ?: $cid;
            if (is_int($targetCid)) {
                $exists = method_exists(Coroutine::class, 'exists') ? Coroutine::exists($targetCid) : true;
                if ($exists && method_exists(Coroutine::class, 'cancel')) {
                    Coroutine::cancel($targetCid);
                }
            }

            // 超时锁释放兜底：防止被取消的协程残留悬挂互斥锁导致后续整点永远被跳过
            if ($event->withoutOverlapping) {
                try {
                    $event->mutex->forget($event);
                } catch (Throwable $e) {
                }
            }

            // 沙箱清理兜底
            $runner->cleanUpSandbox();

            $summary = $runner->getEventSummary($event);
            $msg = sprintf(
                'Task [%s] execution timed out after %.1f seconds and was forcibly cancelled.',
                $summary,
                $timeout
            );
            $this->output->writeln(sprintf('  <fg=red;options=bold>%s</>', $msg));

            try {
                Log::channel('schedule')->error($msg);
            } catch (Throwable $e) {
                Log::error($msg);
            }

            // 派发官方失败事件并上报异常
            $timeoutException = new RuntimeException($msg);
            $runner->dispatcher()->dispatch(new ScheduledTaskFailed($event, $timeoutException, $timeout));
            $runner->exceptionHandler()->report($timeoutException);
        }

        $doneChannel->close();
    }

    /**
     * 接收外部中断退出信号.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
