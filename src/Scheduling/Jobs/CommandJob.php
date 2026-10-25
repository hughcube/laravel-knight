<?php

namespace HughCube\Laravel\Knight\Scheduling\Jobs;

use HughCube\Laravel\Knight\Queue\Job;
use HughCube\Laravel\Knight\Scheduling\Concerns\ManagesEventLifecycle;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Contracts\Container\BindingResolutionException;
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
class CommandJob extends Job implements ShouldQueue
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
    public ?string $user;
    public bool $mutexAcquired = false;

    /**
     * @var Event|null 运行时瞬态 Event 对象（不参与序列化）
     */
    protected ?Event $runtimeEvent = null;

    /**
     * @param  Event  $event
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
        $this->timezone = $event->timezone instanceof \DateTimeZone
            ? $event->timezone->getName()
            : (string) $event->timezone;
        $this->user = ! empty($event->user) ? (string) $event->user : null;
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
        $event->skippedBecauseOverlapping = false;
        $event->exitCode = null;

        // 1. 闭包事件：由 Laravel 原生 run() 自洽处理抢锁、before、执行、after 与解锁
        if ($event instanceof CallbackEvent) {
            // 排他互斥门禁检查：若互斥锁已存在，直接派发 Skipped 事件并退出，绝不误派发 Starting
            if ($event->withoutOverlapping && $event->mutex->exists($event)) {
                $this->getEventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));

                return;
            }

            $start = microtime(true);
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskStarting($event));

            try {
                $exitCode = $this->runCommandSafely($event, $this->getContainer());

                if (! empty($event->skippedBecauseOverlapping)) {
                    $this->getEventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));

                    return;
                }

                if ($exitCode !== 0) {
                    throw new \RuntimeException(sprintf(
                        'Scheduled callback [%s] failed with exit code [%d].',
                        $event->getSummaryForDisplay(),
                        $exitCode
                    ));
                }

                $duration = round(microtime(true) - $start, 2);
                $this->getEventsDispatcher()->dispatch(new ScheduledTaskFinished($event, $duration));
            } catch (Throwable $e) {
                $duration = round(microtime(true) - $start, 2);
                $this->getEventsDispatcher()->dispatch(new ScheduledTaskFailed($event, $e, $duration));
                throw $e;
            } finally {
                if ($event->withoutOverlapping) {
                    $this->releaseMutexSafely($event);
                }
                $this->resetRuntimeState($this->getContainer());
            }

            return;
        }

        // 2. 非闭包命令（Artisan / Shell）：由本调度内核统一管控生命周期与排他互斥
        if (! $this->mutexAcquired && $this->shouldSkipDueToOverlapping($event)) {
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskSkipped($event));

            return;
        }

        $start = microtime(true);
        $this->getEventsDispatcher()->dispatch(new ScheduledTaskStarting($event));

        $exitCode = 0;
        try {
            $exitCode = $this->runCommandSafely($event, $this->getContainer());

            if ($exitCode !== 0) {
                throw new \RuntimeException(sprintf(
                    'Scheduled command [%s] failed with exit code [%d].',
                    $event->command ?: $event->getSummaryForDisplay(),
                    $exitCode
                ));
            }

            $duration = round(microtime(true) - $start, 2);
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskFinished($event, $duration));
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $start, 2);
            $exitCode = 1;
            $this->getEventsDispatcher()->dispatch(new ScheduledTaskFailed($event, $e, $duration));
            throw $e;
        } finally {
            if ($event->withoutOverlapping) {
                $this->releaseMutexSafely($event);
            }
            $this->resetRuntimeState($this->getContainer());
        }
    }

    /**
     * 解析/重建 Event 实例.
     *
     * @return Event
     *
     * @throws BindingResolutionException
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
        $event->user = $this->user;

        return $this->runtimeEvent = $event;
    }

    /**
     * 获取 EventMutex 实例 (兼容非 CLI 容器绑定).
     *
     * @return EventMutex
     *
     * @throws BindingResolutionException
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
     * 自定义序列化属性列表，动态剔除包含闭包的瞬态 runtimeEvent 对象.
     *
     * 完整保留当前 Job、父类以及队列所需的所有元数据属性 (queue, connection, data 等).
     *
     * @return array<string>
     */
    public function __sleep(): array
    {
        $properties = array_keys(get_object_vars($this));

        return array_values(array_diff($properties, ['runtimeEvent']));
    }
}
