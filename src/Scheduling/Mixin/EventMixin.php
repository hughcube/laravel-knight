<?php

namespace HughCube\Laravel\Knight\Scheduling\Mixin;

use Closure;
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
}
