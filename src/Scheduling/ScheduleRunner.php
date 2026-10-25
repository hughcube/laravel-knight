<?php

namespace HughCube\Laravel\Knight\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * 大一统调度执行内核 (ScheduleRunner).
 *
 * 专注于在常驻进程内高效、安全地执行调度任务，消灭子进程冷启动框架开销：
 * 1. 完整门禁闭环：pause 暂停、filtersPass 过滤器与 onOneServer 单机独占锁；
 * 2. 互斥排他锁严密自闭环 (withoutOverlapping)；
 * 3. 双端环境自适应分流：
 *    - 在 HTTP Action 模式 ($asyncToQueue = true)：遇到 runInBackground 耗时任务自动推入队列削峰，死守 HTTP 超时红线；
 *    - 在 CLI Daemon 模式 ($asyncToQueue = false)：普通命令同进程直驱，runInBackground 任务本地后台子进程运行，零队列依赖；
 * 4. 常驻长连接安全保护与残留未提交事务自动回滚。
 */
class ScheduleRunner
{
    /**
     * @var Application|Container 依赖注入容器
     */
    protected $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 执行当前到期的调度任务.
     *
     * @param bool $asyncToQueue 是否将耗时后台任务推入异步队列 (Action 环境传 true, Daemon 环境传 false)
     * @param callable|null $outputCallback 终端输出回调 (供 Daemon 打印执行日志)
     * @param bool $repeatUntilEndOfMinute 是否在当前分钟剩余时间内持续调度秒级重复任务 (Daemon 模式传 true)
     * @param callable|null $shouldInterruptCallback 外部平滑退出回调，返回 true 时立刻中断退出循环
     * @return array 结构化执行简报
     * @throws Throwable
     */
    public function run(
        bool $asyncToQueue = false,
        ?callable $outputCallback = null,
        bool $repeatUntilEndOfMinute = false,
        ?callable $shouldInterruptCallback = null
    ): array {
        $schedule = $this->resolveSchedule();
        $this->clearScheduleMutexCache($schedule);
        $startedAt = Carbon::now()->startOfMinute();

        /** @var Application $app */
        $app = $this->container;

        /** @var Event[] $dueEvents */
        $dueEvents = $schedule->dueEvents($app);

        $summary = [];
        $isPaused = $this->isSchedulePaused();

        foreach ($dueEvents as $event) {
            if ($shouldInterruptCallback !== null && $shouldInterruptCallback()) {
                break;
            }

            if ($this->shouldInterrupt($schedule, $startedAt)) {
                break;
            }

            // 1. 调度暂停门禁检查 (illuminate:schedule:paused)
            if ($isPaused && !(method_exists($event, 'runsWhenPaused') && $event->runsWhenPaused())) {
                $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                continue;
            }

            // 2. 基础过滤器门禁检查 (when / skip / inEnvironment / isDownForMaintenance)
            if (!$event->filtersPass($app)) {
                $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                continue;
            }

            // 3. 多节点单机认领门禁检查 (onOneServer)
            if ($event->onOneServer && !$schedule->serverShouldRun($event, $startedAt)) {
                $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                continue;
            }

            // 4. 执行具体任务项
            $this->executeSingleEvent($event, $asyncToQueue, $outputCallback, $summary);
        }

        // 5. 对齐官方秒级重复任务驱动 (Repeatable Events)
        if ($repeatUntilEndOfMinute) {
            $repeatableEvents = collect($dueEvents)->filter(function ($event) {
                return method_exists($event, 'isRepeatable') && $event->isRepeatable();
            })->all();

            if (!empty($repeatableEvents)) {
                $this->repeatEvents(
                    $repeatableEvents,
                    $startedAt,
                    $asyncToQueue,
                    $outputCallback,
                    $shouldInterruptCallback,
                    $summary
                );
            }
        }

        return [
            'triggered_at' => $startedAt->toDateTimeString(),
            'count'        => count($summary),
            'tasks'        => $summary,
        ];
    }

    /**
     * 执行具体单个任务项 (包含后台分流与同进程直驱).
     *
     * @param Event $event
     * @param bool $asyncToQueue
     * @param callable|null $outputCallback
     * @param array $summary
     */
    protected function executeSingleEvent(
        Event $event,
        bool $asyncToQueue,
        ?callable $outputCallback,
        array &$summary
    ): void {
        // 重置常驻内存下 Event 实例跨轮次可能残留的瞬态执行状态
        $event->skippedBecauseOverlapping = false;
        $event->exitCode = null;

        // A. 判定是否为后台耗时任务 (runInBackground) 且非闭包
        if (!($event instanceof CallbackEvent) && !empty($event->runInBackground)) {
            if ($asyncToQueue) {
                // Action 模式：为防 HTTP 请求超时，自动升阶推入异步队列！
                $job = new CommandJob($event);

                // 排他互斥门禁检查与入队前抢锁 (防队列堆积与并发踩踏)
                if (!empty($event->withoutOverlapping)) {
                    if ($event->mutex->exists($event) || !$event->mutex->create($event)) {
                        $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                        $summary[] = [
                            'task'     => $event->getSummaryForDisplay(),
                            'mode'     => 'skipped',
                            'duration' => 0.0,
                        ];
                        if ($outputCallback !== null) {
                            $outputCallback($event, 'skipped', 0.0);
                        }
                        return;
                    }
                    $job->mutexAcquired = true;
                }

                $onQueue = property_exists($event, 'onQueue') ? $event->onQueue : ($event->onQueue ?? null);
                if (!empty($onQueue)) {
                    $job->onQueue($onQueue);
                }
                $onConnection = property_exists($event, 'onConnection')
                    ? $event->onConnection
                    : ($event->onConnection ?? null);
                if (!empty($onConnection)) {
                    $job->onConnection($onConnection);
                }

                $this->busDispatcher()->dispatch($job);

                $summary[] = [
                    'task'  => $event->getSummaryForDisplay(),
                    'mode'  => 'queue',
                    'queue' => $job->queue ?: 'default',
                ];

                if ($outputCallback !== null) {
                    $outputCallback($event, 'queue', 0.0);
                }
            } else {
                // Daemon 模式：本地后台独立运行，零队列开销
                if (!empty($event->withoutOverlapping) && $event->mutex->exists($event)) {
                    $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                    $summary[] = [
                        'task'     => $event->getSummaryForDisplay(),
                        'mode'     => 'skipped',
                        'duration' => 0.0,
                    ];
                    if ($outputCallback !== null) {
                        $outputCallback($event, 'skipped', 0.0);
                    }
                    return;
                }

                $start = microtime(true);
                $this->eventsDispatcher()->dispatch(new ScheduledTaskStarting($event));

                try {
                    // 原生 run() 内部会自动拼装后台命令并执行
                    $event->run($this->container);

                    $duration = round(microtime(true) - $start, 2);

                    if (!empty($event->skippedBecauseOverlapping)) {
                        $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                        $summary[] = [
                            'task'     => $event->getSummaryForDisplay(),
                            'mode'     => 'skipped',
                            'duration' => $duration,
                        ];
                        if ($outputCallback !== null) {
                            $outputCallback($event, 'skipped', $duration);
                        }
                    } else {
                        $this->eventsDispatcher()->dispatch(new ScheduledTaskFinished($event, $duration));
                        $summary[] = [
                            'task'     => $event->getSummaryForDisplay(),
                            'mode'     => 'background',
                            'duration' => $duration,
                        ];
                        if ($outputCallback !== null) {
                            $outputCallback($event, 'background', $duration);
                        }
                    }
                } catch (Throwable $e) {
                    $duration = round(microtime(true) - $start, 2);
                    $this->eventsDispatcher()->dispatch(new ScheduledTaskFailed($event, $e, $duration));
                    $this->exceptionHandler()->report($e);
                    $summary[] = [
                        'task'     => $event->getSummaryForDisplay(),
                        'mode'     => 'background',
                        'duration' => $duration,
                        'error'    => $e->getMessage(),
                    ];
                    if ($outputCallback !== null) {
                        $outputCallback($event, 'error', $duration);
                    }
                }
            }
            return;
        }

        // B. 普通短任务 / 闭包任务：在当前进程安全执行
        $job = new CommandJob($event);
        $start = microtime(true);

        try {
            $job->handle();

            $duration = round(microtime(true) - $start, 2);
            $summary[] = [
                'task'     => $event->getSummaryForDisplay(),
                'mode'     => 'sync',
                'duration' => $duration,
            ];

            if ($outputCallback !== null) {
                $outputCallback($event, 'sync', $duration);
            }
        } catch (Throwable $e) {
            // 与官方一致：单个任务失败隔离，记录并继续执行后续任务，不中断整批调度
            $duration = round(microtime(true) - $start, 2);
            $this->exceptionHandler()->report($e);
            $summary[] = [
                'task'     => $event->getSummaryForDisplay(),
                'mode'     => 'sync',
                'duration' => $duration,
                'error'    => $e->getMessage(),
            ];

            if ($outputCallback !== null) {
                $outputCallback($event, 'error', $duration);
            }
        }
    }

    /**
     * 持续执行可重复秒级任务直到当前分钟结束 (100% 对齐官方 repeatEvents 契约).
     *
     * @param Event[] $events
     * @param Carbon $startedAt
     * @param bool $asyncToQueue
     * @param callable|null $outputCallback
     * @param callable|null $shouldInterruptCallback
     * @param array $summary
     */
    protected function repeatEvents(
        array $events,
        Carbon $startedAt,
        bool $asyncToQueue,
        ?callable $outputCallback,
        ?callable $shouldInterruptCallback,
        array &$summary
    ): void {
        $endOfMinute = $startedAt->copy()->endOfMinute();
        $hasEnteredMaintenanceMode = false;

        /** @var Application $app */
        $app = $this->container;
        $schedule = $this->resolveSchedule();

        while (Carbon::now()->lte($endOfMinute)) {
            if ($shouldInterruptCallback !== null && $shouldInterruptCallback()) {
                return;
            }

            if ($this->shouldInterrupt($schedule, $startedAt)) {
                return;
            }

            $paused = $this->isSchedulePaused();

            foreach ($events as $event) {
                if ($shouldInterruptCallback !== null && $shouldInterruptCallback()) {
                    return;
                }

                if (method_exists($event, 'shouldRepeatNow') && !$event->shouldRepeatNow()) {
                    continue;
                }

                if (Carbon::now()->gt($endOfMinute)) {
                    return;
                }

                if (method_exists($app, 'isDownForMaintenance')) {
                    $hasEnteredMaintenanceMode = $hasEnteredMaintenanceMode || (bool) $app->isDownForMaintenance();
                    if ($hasEnteredMaintenanceMode
                        && method_exists($event, 'runsInMaintenanceMode')
                        && !$event->runsInMaintenanceMode()
                    ) {
                        continue;
                    }
                }

                if ($paused && !(method_exists($event, 'runsWhenPaused') && $event->runsWhenPaused())) {
                    $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                    continue;
                }

                if (!$event->filtersPass($app)) {
                    $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                    continue;
                }

                if ($event->onOneServer && !$schedule->serverShouldRun($event, $startedAt)) {
                    $this->eventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
                    continue;
                }

                $this->executeSingleEvent($event, $asyncToQueue, $outputCallback, $summary);
            }

            usleep(100 * 1000); // 100ms 轮询检测
        }
    }

    /**
     * 判定当前调度是否被外部命令 (php artisan schedule:interrupt) 中断.
     */
    protected function shouldInterrupt(Schedule $schedule, Carbon $startedAt): bool
    {
        if (method_exists($schedule, 'hasBeenInterruptedSince')) {
            try {
                return (bool) $schedule->hasBeenInterruptedSince($startedAt);
            } catch (Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * 判定当前调度是否处于暂停状态.
     */
    protected function isSchedulePaused(): bool
    {
        if (property_exists(Schedule::class, 'pausable') && !Schedule::$pausable) {
            return false;
        }

        try {
            return (bool) $this->container['cache']->get('illuminate:schedule:paused', false);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 解析并确保 Schedule 实例已装载应用定义的调度任务.
     */
    protected function resolveSchedule(): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = $this->container->make(Schedule::class);

        if (empty($schedule->events())) {
            $this->loadEventsIntoSchedule($schedule);
        }

        return $schedule;
    }

    /**
     * 在非标准 CLI 模式下主动加载 routes/console.php 或 Kernel::schedule 定义的任务.
     */
    protected function loadEventsIntoSchedule(Schedule $schedule): void
    {
        $consoleRoutes = function_exists('base_path')
            ? base_path('routes/console.php')
            : (method_exists($this->container, 'basePath') ? $this->container->basePath('routes/console.php') : null);

        if (!empty($consoleRoutes) && file_exists($consoleRoutes)) {
            require $consoleRoutes;
        }

        if ($this->container->bound(\Illuminate\Contracts\Console\Kernel::class)) {
            $kernel = $this->container->make(\Illuminate\Contracts\Console\Kernel::class);
            if (method_exists($kernel, 'schedule')) {
                $reflection = new ReflectionMethod($kernel, 'schedule');
                $reflection->invoke($kernel, $schedule);
            }
        }
    }

    /**
     * 清理 Schedule 实例内的单机互斥锁内存缓存，防止常驻内存下单机锁被永久复用穿透.
     */
    protected function clearScheduleMutexCache(Schedule $schedule): void
    {
        try {
            $property = new ReflectionProperty($schedule, 'mutexCache');
            $property->setAccessible(true);
            $property->setValue($schedule, []);
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * 获取事件派发器.
     */
    protected function eventsDispatcher(): EventDispatcher
    {
        return $this->container->make(EventDispatcher::class);
    }

    /**
     * 获取任务总线派发器.
     */
    protected function busDispatcher(): BusDispatcher
    {
        return $this->container->make(BusDispatcher::class);
    }

    /**
     * 获取异常报告处理器.
     */
    protected function exceptionHandler(): ExceptionHandler
    {
        return $this->container->make(ExceptionHandler::class);
    }
}
