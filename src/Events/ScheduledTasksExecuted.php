<?php

namespace HughCube\Laravel\Knight\Events;

class ScheduledTasksExecuted
{
    /**
     * @var array 调度执行简报
     */
    public array $report;

    /**
     * @param  array  $report
     */
    public function __construct(array $report)
    {
        $this->report = $report;
    }

    /**
     * @return array
     */
    public function getReport(): array
    {
        return $this->report;
    }
}
