<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\QueuedCommandJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class QueuedCommandJobTest extends TestCase
{
    public function testExecutionAndLifecycleEvents()
    {
        Event::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskFinished::class,
        ]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->exec('echo "Hello Knight"');

        $job = new QueuedCommandJob($event);
        $job->handle();

        Event::assertDispatched(ScheduledTaskStarting::class);
        Event::assertDispatched(ScheduledTaskFinished::class);
    }

    public function testWithoutOverlappingMutex()
    {
        Event::fake([
            ScheduledTaskSkipped::class,
            ScheduledTaskStarting::class,
            ScheduledTaskFinished::class,
        ]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->exec('echo "Hello Knight"')->withoutOverlapping();

        /** @var EventMutex $mutex */
        $mutex = $this->app->make(EventMutex::class);

        // 第一次执行：成功获取锁，执行并释放锁
        $job = new QueuedCommandJob($event);
        $job->handle();

        $this->assertFalse($mutex->exists($event));
        Event::assertDispatched(ScheduledTaskStarting::class);
        Event::assertDispatched(ScheduledTaskFinished::class);

        // 人工占锁，模拟上一轮任务还在运行
        $mutex->create($event);
        $this->assertTrue($mutex->exists($event));

        // 第二次执行：应检测到锁存在，直接跳过并派发 Skipped 事件
        $job2 = new QueuedCommandJob($event);
        $job2->handle();

        Event::assertDispatched(ScheduledTaskSkipped::class);

        // 锁依然保持占用，最后手动清理
        $this->assertTrue($mutex->exists($event));
        $mutex->forget($event);
    }

    public function testSerialization()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('inspire')->withoutOverlapping(30)->sendOutputTo('/tmp/test.log');

        $job = new QueuedCommandJob($event);
        $serialized = serialize($job);
        $this->assertNotEmpty($serialized);

        /** @var QueuedCommandJob $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(QueuedCommandJob::class, $unserialized);
        $this->assertSame($job->command, $unserialized->command);
        $this->assertTrue($unserialized->withoutOverlapping);
        $this->assertSame(30, $unserialized->expiresAt);
        $this->assertSame('/tmp/test.log', $unserialized->output);

        $resolvedEvent = $unserialized->resolveEvent();
        $this->assertSame($job->command, $resolvedEvent->command);
    }
}
