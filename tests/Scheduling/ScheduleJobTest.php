<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\EventDyeManager;
use HughCube\Laravel\Knight\Scheduling\QueuedCommandJob;
use HughCube\Laravel\Knight\Scheduling\ScheduleJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

class ScheduleJobTest extends TestCase
{
    protected function tearDown(): void
    {
        EventDyeManager::flush();
        parent::tearDown();
    }

    public function testScheduleJobDispatchesProperly()
    {
        Queue::fake();

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        // 1. 简单任务 (未染色)
        $schedule->exec('echo "Simple Task"')->everyMinute();

        // 2. 复杂任务 (染色 runInQueue)
        $schedule->exec('echo "Heavy Task"')->everyMinute()->runInQueue('heavy_queue', 'redis');

        $job = new ScheduleJob();
        $job->handle();
        $report = $job->getReport();

        $this->assertArrayHasKey('triggered_at', $report);
        $this->assertSame(2, $report['count']);

        $modes = array_column($report['tasks'], 'mode');
        $this->assertContains('sync', $modes);
        $this->assertContains('queue', $modes);

        // 验证复杂任务确实被 push 到了队列
        Queue::assertPushed(QueuedCommandJob::class, function (QueuedCommandJob $job) {
            return $job->queue === 'heavy_queue' && $job->connection === 'redis';
        });
    }
}
