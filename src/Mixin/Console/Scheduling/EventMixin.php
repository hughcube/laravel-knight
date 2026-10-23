<?php

namespace HughCube\Laravel\Knight\Mixin\Console\Scheduling;

use Closure;
use HughCube\Laravel\Knight\Scheduling\EventDyeManager;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;

/**
 * @mixin-target \Illuminate\Console\Scheduling\Event
 */
class EventMixin
{
    /**
     * 设置日志输出路径，通过回调函数动态生成路径.
     *
     * 使用示例:
     *   Schedule::command('app:example')
     *       ->sendOutputToDynamic(function () {
     *           return log_path(sprintf('crontab-example-%s.log', date('Y-m-d')));
     *       })
     *       ->cron('* * * * *');
     *
     * @return Closure
     */
    public function sendOutputToDynamic(): Closure
    {
        return function (callable $callback, $append = true) {
            return $this->before(function () use ($callback, $append) {
                /** @var ScheduleEvent $this */
                $this->sendOutputTo($callback(), $append);
            });
        };
    }

    /**
     * 将命令染色为在异步队列中运行（与官方原生 runInBackground 形成对称美学）.
     *
     * 使用示例:
     *   Schedule::command('order:sync')->everyFiveMinutes()->withoutOverlapping()->runInQueue();
     *   Schedule::command('report:generate')->dailyAt('02:00')->runInQueue('reports', 'redis');
     *
     * @return Closure
     */
    public function runInQueue(): Closure
    {
        return function (?string $queue = null, ?string $connection = null) {
            /** @var ScheduleEvent $this */
            EventDyeManager::dye($this, true, $queue, $connection);
            return $this;
        };
    }

    /**
     * 别名支持：onQueue()
     *
     * @return Closure
     */
    public function onQueue(): Closure
    {
        return $this->runInQueue();
    }

    /**
     * 别名支持：queued()
     *
     * @return Closure
     */
    public function queued(): Closure
    {
        return $this->runInQueue();
    }
}
