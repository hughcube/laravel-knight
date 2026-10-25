<?php

namespace HughCube\Laravel\Knight\Scheduling\Jobs;

use HughCube\Laravel\Knight\Queue\Job;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use Throwable;

/**
 * 大一统调度分发 Job (解耦运行环境).
 *
 * 供 HTTP 心跳 (/devops/schedule) 或 FC (/invoke) 触发时总线派发执行。
 * 内部委托 ScheduleRunner 驱动执行，遇到 runInBackground 耗时任务自动升阶入队。
 */
class ScheduleJob extends Job
{
    /**
     * @var array 调度执行结果简报
     */
    protected array $report = [];

    /**
     * @var array 最近一次调度执行结果简报 (供总线同步派发场景获取)
     */
    protected static array $lastReport = [];

    /**
     * @return void
     * @throws Throwable
     */
    protected function action(): void
    {
        $runner = new ScheduleRunner($this->getContainer());

        // Action / ScheduleJob 上下文：开启 $asyncToQueue = true 防 HTTP 超时
        $this->report = $runner->run(true);
        static::$lastReport = $this->report;

        if (0 < ($this->report['count'] ?? 0)) {
            $this->info(sprintf(
                'Schedule executed [%d] tasks at [%s].',
                $this->report['count'],
                $this->report['triggered_at'] ?? ''
            ));
        }
    }

    /**
     * 获取执行简报.
     *
     * @return array
     */
    public function getReport(): array
    {
        return !empty($this->report) ? $this->report : static::$lastReport;
    }

    /**
     * 清理最近一次执行简报缓存 (避免常驻内存跨请求污染).
     */
    public static function clearLastReport(): void
    {
        static::$lastReport = [];
    }
}
