<?php

namespace HughCube\Laravel\Knight\Scheduling;

use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Throwable;

/**
 * 大一统调度分发 Job (解耦运行环境).
 *
 * 无论是通过 HTTP 心跳 (/devops/schedule) 触发，还是通过 FC (/invoke) 触发，
 * 统一执行本 Job 解析 routes/console.php 中的到期任务。
 *
 * 核心特性：
 * 1. 简单任务 (未染色)：就地同步执行，耗时毫秒级，复用当前 Web 进程长连接
 * 2. 复杂任务 (染色 runInQueue)：推入标准异步队列，后台 Worker 削峰消费
 * 3. 统一由 QueuedCommandJob 驱动生命周期与排他互斥，实现零逻辑冗余
 */
class ScheduleJob extends Job
{
    /**
     * @var array 调度执行结果简报
     */
    protected array $report = [];

    /**
     * @return void
     * @throws Throwable
     */
    protected function action(): void
    {
        $schedule = $this->resolveSchedule();

        $now = Carbon::now()->startOfMinute();
        /** @phpstan-ignore-next-line */
        $dueEvents = $schedule->dueEvents($this->getContainer());

        $summary = [];

        foreach ($dueEvents as $event) {
            // 基础门禁检查 (when / skip / onOneServer / isDownForMaintenance)
            if (!$event->filtersPass($this->getContainer())) {
                continue;
            }

            // 统一实例化执行载体 Job
            $job = new QueuedCommandJob($event);

            // 根据染色状态选择分发通道
            if (EventDyeManager::shouldRunInQueue($event)) {
                $options = EventDyeManager::getQueueOptions($event);
                if (!empty($options['queue'])) {
                    $job->onQueue($options['queue']);
                }
                if (!empty($options['connection'])) {
                    $job->onConnection($options['connection']);
                }

                $this->getDispatcher()->dispatch($job);

                $summary[] = [
                    'task'  => $event->getSummaryForDisplay(),
                    'mode'  => 'queue',
                    'queue' => $options['queue'] ?: 'default',
                ];
            } else {
                // 简单任务：就地同步执行 (毫秒级)
                $job->handle();

                $summary[] = [
                    'task' => $event->getSummaryForDisplay(),
                    'mode' => 'sync',
                ];
            }
        }

        $this->report = [
            'triggered_at' => $now->toDateTimeString(),
            'count'        => count($summary),
            'tasks'        => $summary,
        ];
    }

    /**
     * 获取执行简报.
     *
     * @return array
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * 解析并确保 Schedule 实例已装载应用定义的调度任务.
     *
     * @return Schedule
     */
    protected function resolveSchedule(): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = $this->getContainer()->make(Schedule::class);

        if (empty($schedule->events())) {
            $this->loadEventsIntoSchedule($schedule);
        }

        return $schedule;
    }

    /**
     * 在非 CLI 模式下主动加载 routes/console.php 或 Kernel::schedule 定义的任务.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function loadEventsIntoSchedule(Schedule $schedule): void
    {
        $consoleRoutes = function_exists('base_path')
            ? base_path('routes/console.php')
            : (method_exists($this->getContainer(), 'basePath') ? $this->getContainer()->basePath('routes/console.php') : null);

        if (!empty($consoleRoutes) && file_exists($consoleRoutes)) {
            require $consoleRoutes;
        }

        if ($this->getContainer()->bound(\Illuminate\Contracts\Console\Kernel::class)) {
            $kernel = $this->getContainer()->make(\Illuminate\Contracts\Console\Kernel::class);
            if (method_exists($kernel, 'schedule')) {
                $reflection = new ReflectionMethod($kernel, 'schedule');
                $reflection->invoke($kernel, $schedule);
            }
        }
    }
}
