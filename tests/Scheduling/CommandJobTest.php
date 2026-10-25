<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class CommandJobTest extends TestCase
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

        $job = new CommandJob($event);
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
        $job = new CommandJob($event);
        $job->handle();

        $this->assertFalse($mutex->exists($event));
        Event::assertDispatched(ScheduledTaskStarting::class);
        Event::assertDispatched(ScheduledTaskFinished::class);

        // 人工占锁，模拟上一轮任务还在运行
        $mutex->create($event);
        $this->assertTrue($mutex->exists($event));

        // 第二次执行：应检测到锁存在，直接跳过并派发 Skipped 事件
        $job2 = new CommandJob($event);
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

        $job = new CommandJob($event);
        $serialized = serialize($job);
        $this->assertNotEmpty($serialized);

        /** @var CommandJob $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(CommandJob::class, $unserialized);
        $this->assertSame($job->command, $unserialized->command);
        $this->assertTrue($unserialized->withoutOverlapping);
        $this->assertSame(30, $unserialized->expiresAt);
        $this->assertSame('/tmp/test.log', $unserialized->output);

        $resolvedEvent = $unserialized->resolveEvent();
        $this->assertSame($job->command, $resolvedEvent->command);
    }

    public function testCallbackEventWithWithoutOverlappingExecutesSuccessfully()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $event = $schedule->call(function () use (&$executed) {
            $executed = true;
            return 0;
        })->name('test-closure-task')->withoutOverlapping();

        $job = new CommandJob($event);
        $job->handle();

        $this->assertTrue($executed, '带有 withoutOverlapping 的闭包任务应当被成功执行，不应被二次抢锁误杀');
        $this->assertFalse($event->mutex->exists($event), '任务执行后互斥锁应当被安全释放');
    }

    /**
     * 测试带有 Shell 管道或重定向的 Artisan 命令能正确识别并降级为 Process 执行，不强行内存直驱.
     */
    public function testArtisanCommandWithPipesOrRedirectionDegradesToProcess()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = $schedule->command('inspire | grep -i a');
        $job = new CommandJob($event);

        $reflection = new \ReflectionMethod($job, 'shouldRunInCurrentProcess');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        // 验证含有管道符 '|' 的命令判定为不应在当前进程直驱
        $this->assertFalse($reflection->invoke($job, $event));

        $cleanEvent = $schedule->command('inspire');
        $cleanJob = new CommandJob($cleanEvent);

        // 验证纯正 Artisan 命令判定为可以在当前进程直驱
        $this->assertTrue($reflection->invoke($cleanJob, $cleanEvent));
    }

    /**
     * 测试闭包任务排他锁已存在时只派发 Skipped 事件，绝不误派发 Starting 事件.
     */
    public function testCallbackEventSkippedWhenMutexExistsDoesNotDispatchStarting()
    {
        Event::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFinished::class,
        ]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $executed = false;
        $event = $schedule->call(function () use (&$executed) {
            $executed = true;
        })->name('test-overlapping-callback')->withoutOverlapping();

        // 人工占用互斥锁
        $event->mutex->create($event);

        $job = new CommandJob($event);
        $job->handle();

        $this->assertFalse($executed);
        Event::assertDispatched(ScheduledTaskSkipped::class);
        Event::assertNotDispatched(ScheduledTaskStarting::class);
        Event::assertNotDispatched(ScheduledTaskFinished::class);

        $event->mutex->forget($event);
    }

    /**
     * 测试带空格和引号的 php/artisan 二进制路径能被正确解析与剥离.
     */
    public function testExtractArtisanCommandHandlesPathsWithSpacesAndQuotes()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('inspire');
        $job = new CommandJob($event);

        $reflection = new \ReflectionMethod($job, 'extractArtisanCommand');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        // 测试包含空格的双引号路径
        $rawCommand = '"C:\Program Files\PHP\php.exe" "C:\My Projects\artisan" test:run --opt=artisan';
        $extracted = $reflection->invoke($job, $rawCommand);
        $this->assertSame('test:run --opt=artisan', $extracted);

        // 测试包含空格的单引号路径
        $rawCommand2 = "'/opt/custom php/bin/php' '/data/my site/artisan' queue:work --queue=default";
        $extracted2 = $reflection->invoke($job, $rawCommand2);
        $this->assertSame('queue:work --queue=default', $extracted2);
    }

    /**
     * 测试序列化与反序列化时完整保留 user 属性.
     */
    public function testSerializationPreservesUser()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('inspire')->user('forge');

        $job = new CommandJob($event);
        $serialized = serialize($job);

        /** @var CommandJob $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertSame('forge', $unserialized->user);

        $resolvedEvent = $unserialized->resolveEvent();
        $this->assertSame('forge', $resolvedEvent->user);
    }
}
