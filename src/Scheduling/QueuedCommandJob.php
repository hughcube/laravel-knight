<?php

namespace HughCube\Laravel\Knight\Scheduling;

use HughCube\Laravel\Knight\Queue\Job;
use HughCube\Laravel\Knight\Scheduling\Concerns\ManagesEventLifecycle;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * 调度命令执行内核 Job.
 *
 * 作为所有调度任务（无论同步就地执行还是推入异步队列）的统一执行实体。
 * 完整自闭环封装了：
 * 1. 互斥锁检测与抢占 (withoutOverlapping)
 * 2. 官方生命周期 4 大事件触发
 * 3. 任务安全执行与输出捕获
 * 4. 退出时的互斥锁安全释放与事务安全兜底 (保留长连接复用)
 */
class QueuedCommandJob extends Job implements ShouldQueue
{
    use ManagesEventLifecycle;

    public string $command;
    public string $expression;
    public bool $withoutOverlapping;
    public int $expiresAt;
    public string $mutexName;
    public ?string $output;
    public bool $shouldAppendOutput;
    public ?string $description;
    public ?string $timezone;

    /**
     * @var Event|null 运行时瞬态 Event 对象（不参与序列化）
     */
    protected ?Event $runtimeEvent = null;

    /**
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->command = (string) $event->command;
        $this->expression = (string) $event->expression;
        $this->withoutOverlapping = (bool) $event->withoutOverlapping;
        $this->expiresAt = (int) $event->expiresAt;
        $this->mutexName = $event->mutexName();
        $this->output = $event->output;
        $this->shouldAppendOutput = (bool) $event->shouldAppendOutput;
        $this->description = $event->description;
        $this->timezone = $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : (string) $event->timezone;

        $this->runtimeEvent = $event;
    }

    /**
     * 执行具体任务.
     *
     * @throws Throwable
     */
    protected function action(): void
    {
        $event = $this->resolveEvent();

        // 1. 互斥排他判定：如果配置了 withoutOverlapping 且锁已被占
        if ($this->shouldSkipDueToOverlapping($event)) {
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));
            return;
        }

        // 2. 抢占互斥锁
        if ($event->withoutOverlapping) {
            $event->mutex->create($event);
        }

        $start = microtime(true);
        $this->getEventsDispatcher()->dispatch(new ScheduledTaskStarting($event));

        $exitCode = 0;
        try {
            // 3. 执行任务
            $exitCode = $this->runCommandSafely($event, $this->getContainer());

            // 4. 派发 Finished 事件
            $duration = round(microtime(true) - $start, 2);
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskFinished($event, $duration));
        } catch (Throwable $e) {
            $exitCode = 1;
            // 5. 异常上报与未提交事务兜底回滚 (不断开长连接)
            $this->rollbackUncommittedTransactions($this->getContainer());
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskFailed($event, $e));
            $this->getExceptionHandler()->report($e);
            throw $e;
        } finally {
            // 6. 100% 确保在 finally 中释放互斥锁
            if ($event->withoutOverlapping) {
                $this->releaseMutexSafely($event);
            }
        }
    }

    /**
     * 解析/重建 Event 实例.
     *
     * @return Event
     */
    public function resolveEvent(): Event
    {
        if ($this->runtimeEvent !== null) {
            return $this->runtimeEvent;
        }

        $mutex = $this->getEventMutex();
        $event = new Event($mutex, $this->command, $this->timezone ?: null);
        $event->expression = $this->expression;
        $event->withoutOverlapping = $this->withoutOverlapping;
        $event->expiresAt = $this->expiresAt;
        $event->output = $this->output;
        $event->shouldAppendOutput = $this->shouldAppendOutput;
        $event->description = $this->description;

        return $this->runtimeEvent = $event;
    }

    /**
     * 获取 EventMutex 实例 (兼容非 CLI 容器绑定).
     *
     * @return EventMutex
     */
    public function getEventMutex(): EventMutex
    {
        $container = $this->getContainer();
        if ($container->bound(EventMutex::class)) {
            return $container->make(EventMutex::class);
        }

        return $container->make(\Illuminate\Console\Scheduling\CacheEventMutex::class);
    }

    /**
     * 自定义序列化属性，避开 Event 内闭包引发的序列化异常.
     *
     * @return array<string>
     */
    public function __sleep(): array
    {
        return [
            'command',
            'expression',
            'withoutOverlapping',
            'expiresAt',
            'mutexName',
            'output',
            'shouldAppendOutput',
            'description',
            'timezone',
            'job',
            'data',
            'queue',
            'connection',
        ];
    }
}
