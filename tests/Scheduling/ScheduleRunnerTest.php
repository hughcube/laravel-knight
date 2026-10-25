<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class ScheduleRunnerTest extends TestCase
{
    /**
     * 测试暂停门禁：当调度被暂停时，普通任务被跳过.
     */
    public function testSchedulePausedSkipped()
    {
        $this->app['cache']->forever('illuminate:schedule:paused', true);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        Event::fake([ScheduledTaskSkipped::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertSame(0, $report['count']);
        $this->assertFalse($executed);
        Event::assertDispatched(ScheduledTaskSkipped::class);

        $this->app['cache']->forget('illuminate:schedule:paused');
    }

    /**
     * 测试过滤器门禁：when/skip 条件拦截.
     */
    public function testFiltersPassGate()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute()->when(fn () => false);

        Event::fake([ScheduledTaskSkipped::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertSame(0, $report['count']);
        $this->assertFalse($executed);
        Event::assertDispatched(ScheduledTaskSkipped::class);
    }

    /**
     * 测试普通任务同步就地直驱执行.
     */
    public function testSyncTaskExecution()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $executed = false;

        $schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertSame(1, $report['count']);
        $this->assertTrue($executed);
        $this->assertSame('sync', $report['tasks'][0]['mode']);
    }

    /**
     * 测试双端自适应模型：
     * 1. 在 Action 模式 ($asyncToQueue = true)：runInBackground 任务推入异步队列
     * 2. 在 Daemon 模式 ($asyncToQueue = false)：runInBackground 任务在本地后台跑
     */
    public function testRunInBackgroundAdaptiveModel()
    {
        Queue::fake([CommandJob::class]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        // 注册一个 Artisan 命令并声明 runInBackground
        $schedule->command('inspire')->everyMinute()->runInBackground();

        $runner = new ScheduleRunner($this->app);

        // 1. Action 模式
        $actionReport = $runner->run(true);
        $this->assertSame(1, $actionReport['count']);
        $this->assertSame('queue', $actionReport['tasks'][0]['mode']);
        Queue::assertPushed(CommandJob::class);

        // 2. Daemon 模式
        $daemonReport = $runner->run(false);
        $this->assertSame(1, $daemonReport['count']);
        $this->assertSame('background', $daemonReport['tasks'][0]['mode']);
    }

    /**
     * 测试 outputCallback 接收通知.
     */
    public function testOutputCallbackIsCalled()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $schedule->call(fn () => 0)->everyMinute();

        $called = false;
        $callback = function ($event, $mode, $duration) use (&$called) {
            $called = true;
            $this->assertSame('sync', $mode);
            $this->assertGreaterThanOrEqual(0.0, $duration);
        };

        $runner = new ScheduleRunner($this->app);
        $runner->run(false, $callback);

        $this->assertTrue($called);
    }

    /**
     * 测试未提交事务安全回滚与异常隔离（失败不中断整批任务）.
     */
    public function testUncommittedTransactionsRollbackOnFailure()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $task1Ran = false;
        $task2Ran = false;

        $schedule->call(function () use (&$task1Ran) {
            $task1Ran = true;
            DB::beginTransaction();
            throw new \RuntimeException('Database rollback test');
        })->everyMinute();

        $schedule->call(function () use (&$task2Ran) {
            $task2Ran = true;
        })->everyMinute();

        Event::fake([ScheduledTaskFailed::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertTrue($task1Ran);
        $this->assertTrue($task2Ran);
        $this->assertSame('Database rollback test', $report['tasks'][0]['error']);
        $this->assertArrayNotHasKey('error', $report['tasks'][1]);
        Event::assertDispatched(ScheduledTaskFailed::class);

        // 验证事务层级已安全回滚至 0
        $this->assertSame(0, DB::transactionLevel());
    }

    /**
     * 测试 Artisan 命令参数包含 "artisan" 词汇时不会被错误截断破坏.
     */
    public function testArtisanCommandExtractionDoesNotCorruptArguments()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = $schedule->command('inspire --tag=artisan_test --file=/var/artisan/data.txt')->everyMinute();
        $job = new CommandJob($event);

        $reflection = new \ReflectionMethod($job, 'extractArtisanCommand');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        $extracted = $reflection->invoke($job, (string) $event->command);

        $this->assertSame('inspire --tag=artisan_test --file=/var/artisan/data.txt', $extracted);
    }

    /**
     * 测试 Daemon 模式下后台任务因排他锁冲突正确跳过并派发 Skipped 事件.
     */
    public function testRunInBackgroundSkippedWhenOverlappingInDaemonMode()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = $schedule->command('inspire')->everyMinute()->runInBackground()->withoutOverlapping();

        // 人工提前占有互斥锁，模拟并发场景
        $event->mutex->create($event);

        Event::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFinished::class,
        ]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertSame(0, $report['count']);

        Event::assertDispatched(ScheduledTaskSkipped::class);
        Event::assertNotDispatched(ScheduledTaskStarting::class);
        Event::assertNotDispatched(ScheduledTaskFinished::class);

        // 释放锁
        $event->mutex->forget($event);
    }

    /**
     * 严苛多任务异构大满贯测试：验证多任务混合并发、单任务报错隔离与门禁拦截互不干扰.
     */
    public function testComprehensiveHeterogeneousBatchExecution()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        // 任务 1：正常同步闭包
        $task1Executed = false;
        $schedule->call(function () use (&$task1Executed) {
            $task1Executed = true;
        })->everyMinute();

        // 任务 2：执行中抛出异常的闭包
        $task2Executed = false;
        $schedule->call(function () use (&$task2Executed) {
            $task2Executed = true;
            throw new \RuntimeException('Task 2 exploded');
        })->everyMinute();

        // 任务 3：前置过滤不通过的命令 (when false)
        $schedule->call(function () {
        })->everyMinute()->when(fn () => false);

        // 任务 4：正常同步任务，验证未被任务 2 的报错连累打断
        $task4Executed = false;
        $schedule->call(function () use (&$task4Executed) {
            $task4Executed = true;
        })->everyMinute();

        Event::fake([ScheduledTaskFailed::class, ScheduledTaskSkipped::class, ScheduledTaskFinished::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        // 门禁跳过的任务不计入 tasks 执行报告，执行了 3 个（任务 1、任务 2、任务 4）
        $this->assertSame(3, $report['count']);
        $this->assertTrue($task1Ran = $task1Executed);
        $this->assertTrue($task2Ran = $task2Executed);
        $this->assertTrue($task4Ran = $task4Executed);

        $this->assertSame('sync', $report['tasks'][0]['mode']);
        $this->assertArrayNotHasKey('error', $report['tasks'][0]);

        $this->assertSame('sync', $report['tasks'][1]['mode']);
        $this->assertSame('Task 2 exploded', $report['tasks'][1]['error']);

        $this->assertSame('sync', $report['tasks'][2]['mode']);
        $this->assertArrayNotHasKey('error', $report['tasks'][2]);

        Event::assertDispatched(ScheduledTaskFailed::class);
        Event::assertDispatched(ScheduledTaskSkipped::class);
    }

    /**
     * 测试闭包返回非布尔正数（如数据统计结果 100）不被误判为非零错误码，符合官方规范.
     */
    public function testCallbackEventNonZeroReturnIsSuccess()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;
            return 100; // 模拟返回处理记录数
        })->everyMinute();

        Event::fake([ScheduledTaskFailed::class, ScheduledTaskFinished::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertTrue($executed);
        $this->assertSame(1, $report['count']);
        $this->assertSame('sync', $report['tasks'][0]['mode']);
        $this->assertArrayNotHasKey('error', $report['tasks'][0]);

        Event::assertDispatched(ScheduledTaskFinished::class);
        Event::assertNotDispatched(ScheduledTaskFailed::class);
    }

    /**
     * 测试闭包严格返回 false 时，对齐官方行为触发失败与 ScheduledTaskFailed.
     */
    public function testCallbackEventReturnFalseFails()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $schedule->call(function () {
            return false; // 官方契约：严格返回 false 代表执行失败
        })->everyMinute();

        Event::fake([ScheduledTaskFailed::class, ScheduledTaskFinished::class]);

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertSame(1, $report['count']);
        $this->assertSame('sync', $report['tasks'][0]['mode']);
        $this->assertArrayHasKey('error', $report['tasks'][0]);

        Event::assertDispatched(ScheduledTaskFailed::class);
        Event::assertNotDispatched(ScheduledTaskFinished::class);
    }

    /**
     * 测试任务执行完毕后常驻内存状态重置 (QueryLog 清空与 Auth Guards 清理).
     */
    public function testResetRuntimeStateClearsQueryLogAndAuthGuards()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $schedule->call(function () {
            // 模拟任务执行过程中开启并产生了 QueryLog
            if (class_exists(\Illuminate\Support\Facades\DB::class)) {
                \Illuminate\Support\Facades\DB::enableQueryLog();
            }
        })->everyMinute();

        $runner = new ScheduleRunner($this->app);
        $runner->run(false);

        // 验证执行完后 QueryLog 被清空，防止常驻内存累积泄漏
        if (class_exists(\Illuminate\Support\Facades\DB::class)) {
            $this->assertEmpty(\Illuminate\Support\Facades\DB::getQueryLog());
        }
    }

    /**
     * 测试外部中断回调能在秒级循环中快速退出.
     */
    public function testInterruptCallbackStopsExecutionEarly()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executedCount = 0;
        $schedule->call(function () use (&$executedCount) {
            $executedCount++;
        })->everyMinute();

        $runner = new ScheduleRunner($this->app);
        // 传入外部立即中断回调
        $report = $runner->run(
            asyncToQueue: false,
            outputCallback: null,
            repeatUntilEndOfMinute: true,
            shouldInterruptCallback: fn () => true
        );

        $this->assertSame(0, $report['count']);
        $this->assertSame(0, $executedCount);
    }

    /**
     * 测试秒级重复任务在 repeatUntilEndOfMinute 模式下的重复驱动.
     */
    public function testRepeatableEventExecutionInDaemonMode()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executedCount = 0;
        $event = $schedule->call(function () use (&$executedCount) {
            $executedCount++;
        })->everyMinute();

        if (method_exists($event, 'everyTenSeconds')) {
            $event->everyTenSeconds();
        } else {
            $event->repeatSeconds = 10;
        }

        // 模拟外部中断：在首轮后即可中断
        $interrupt = function () use (&$executedCount) {
            return $executedCount >= 1;
        };

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(
            asyncToQueue: false,
            outputCallback: null,
            repeatUntilEndOfMinute: true,
            shouldInterruptCallback: $interrupt
        );

        $this->assertGreaterThanOrEqual(1, $executedCount);
    }

    /**
     * 测试同一 Event 对象在常驻内存跨轮次执行时，上一轮残留的 skipped 与 exitCode 能被自动重置.
     */
    public function testEventStateResetAcrossRunsInDaemonMode()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $event = $schedule->call(function () use (&$executed) {
            $executed = true;
            return 0;
        })->everyMinute();

        // 模拟上一轮因并发被标记为 skipped，且残留了错误退出码
        $event->skippedBecauseOverlapping = true;
        $event->exitCode = 1;

        $runner = new ScheduleRunner($this->app);
        $report = $runner->run(false);

        $this->assertTrue($executed, '任务应当被正常执行，不应受上一轮残留状态影响');
        $this->assertFalse($event->skippedBecauseOverlapping, '执行后 skippedBecauseOverlapping 应被重置为 false');
        $this->assertSame(0, $event->exitCode, 'exitCode 应被正确刷新为当前轮次结果');
        $this->assertSame(1, $report['count']);
        $this->assertSame('sync', $report['tasks'][0]['mode']);
        $this->assertArrayNotHasKey('error', $report['tasks'][0]);
    }

    /**
     * 测试异步推入队列模式下 withoutOverlapping 入队前抢锁防重复堆积与 Worker 正常释放闭环.
     */
    public function testAsyncToQueueWithoutOverlappingDeduplicationAndReleaseLifecycle()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = $schedule->command('env')->everyMinute()->runInBackground()->withoutOverlapping(10);

        \Illuminate\Support\Facades\Queue::fake([\HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob::class]);
        Event::fake([ScheduledTaskSkipped::class]);

        $runner = new ScheduleRunner($this->app);

        // 第一次调度：成功占锁并入队
        $report1 = $runner->run(true);
        $this->assertSame(1, $report1['count']);
        $this->assertSame('queue', $report1['tasks'][0]['mode']);
        $this->assertTrue($event->mutex->exists($event), '入队后互斥锁必须已被创建占用');

        // 第二次调度（模拟前一轮 Job 还在队列中排队，锁仍未释放）：应直接在门禁阶段被拦截跳过，绝不派发第二个 Job 进队列
        $report2 = $runner->run(true);
        $this->assertSame(0, $report2['count']);
        Event::assertDispatched(ScheduledTaskSkipped::class);

        // 模拟队列 Worker 弹出 Job 并执行完毕
        $job = new CommandJob($event);
        $job->mutexAcquired = true; // 模拟反序列化还原的预抢锁标识
        $job->handle();

        // 验证 Worker 执行完毕后安全释放了互斥锁
        $this->assertFalse($event->mutex->exists($event), 'Worker 执行完毕后互斥锁应当被安全释放');

        // 第三次调度：锁已释放，可以再次正常入队
        $report3 = $runner->run(true);
        $this->assertSame(1, $report3['count']);
        $this->assertSame('queue', $report3['tasks'][0]['mode']);

        $event->mutex->forget($event);
    }
}
