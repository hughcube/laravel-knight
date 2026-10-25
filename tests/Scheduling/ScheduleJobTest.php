<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob;
use HughCube\Laravel\Knight\Scheduling\Jobs\ScheduleJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class ScheduleJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function testScheduleJobDispatchesProperly()
    {
        Queue::fake();

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        // 1. 简单任务 (未标记 runInBackground)
        $schedule->exec('echo "Simple Task"')->everyMinute();

        // 2. 耗时后台任务 (声明 runInBackground)
        $schedule->exec('echo "Heavy Task"')->everyMinute()->runInBackground();

        $job = new ScheduleJob();
        $job->handle();
        $report = $job->getReport();

        $this->assertArrayHasKey('triggered_at', $report);
        $this->assertSame(2, $report['count']);

        $modes = array_column($report['tasks'], 'mode');
        $this->assertContains('sync', $modes);
        $this->assertContains('queue', $modes);

        // 验证 runInBackground 任务确实被自动推入队列防 HTTP 超时
        Queue::assertPushed(CommandJob::class);
    }

    public function testScheduleJobHonorsPause()
    {
        Event::fake([ScheduledTaskSkipped::class]);

        Cache::put('illuminate:schedule:paused', true);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed1 = false;
        $event1 = $schedule->call(function () use (&$executed1) {
            $executed1 = true;
        })->everyMinute();

        $job = new ScheduleJob();
        $job->handle();

        $this->assertFalse($executed1, '处于暂停状态时任务不应执行');
        Event::assertDispatched(ScheduledTaskSkipped::class);
    }

    public function testScheduleJobHonorsOnOneServer()
    {
        Event::fake([ScheduledTaskSkipped::class]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $event = $schedule->call(function () use (&$executed) {
            $executed = true;
        })->name('unique-test-task')->everyMinute()->onOneServer();

        // 预先占有单机排他锁，模拟已被其他服务器/容器抢占
        $mutex = $this->app->make(\Illuminate\Console\Scheduling\SchedulingMutex::class);
        $mutex->create($event, Carbon::now()->startOfMinute());

        $job = new ScheduleJob();
        $job->handle();

        $this->assertFalse($executed, '在其他服务器已运行时单机任务不应被重复执行');
        Event::assertDispatched(ScheduledTaskSkipped::class);
    }

    public function testScheduleJobForcesSyncForCallbackEvent()
    {
        Queue::fake();

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        $job = new ScheduleJob();
        $job->handle();
        $report = $job->getReport();

        $this->assertTrue($executed);
        $this->assertSame('sync', $report['tasks'][0]['mode']);
        Queue::assertNothingPushed();
    }
}
