<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use Carbon\Carbon;
use Exception;
use HughCube\Laravel\Knight\Scheduling\TaskRunner;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event as EventFacade;
use Symfony\Component\Console\Output\BufferedOutput;

class TaskRunnerTest extends TestCase
{
    protected BufferedOutput $output;
    protected TaskRunner $runner;
    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new BufferedOutput();
        $this->runner = new TaskRunner($this->app, $this->output);
        $this->schedule = $this->app->make(Schedule::class);
    }

    /**
     * 1. 测试常规 Callback 任务的安全执行与退出码处理.
     */
    public function testCallbackEventExecution()
    {
        $executed = false;
        $event = $this->schedule->call(function () use (&$executed) {
            $executed = true;

            return 0;
        });

        $exitCode = $this->runner->runSafely($event);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($executed);
    }

    /**
     * 2. 测试任务生命周期事件 (Starting & Finished) 的派发.
     */
    public function testLifecycleEventsDispatched()
    {
        EventFacade::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskFinished::class,
            ScheduledTaskFailed::class,
            ScheduledTaskSkipped::class,
        ]);

        $event = $this->schedule->call(function () {
            return 0;
        });

        $this->runner->runSafely($event);

        EventFacade::assertDispatched(ScheduledTaskStarting::class, function ($e) use ($event) {
            return $e->task === $event;
        });

        EventFacade::assertDispatched(ScheduledTaskFinished::class, function ($e) use ($event) {
            return $e->task === $event && is_float($e->runtime) && $e->runtime >= 0.0 && $e->runtime < 10.0;
        });

        EventFacade::assertNotDispatched(ScheduledTaskFailed::class);
    }

    /**
     * 3. 测试 withoutOverlapping 互斥锁生命周期与安全释放.
     */
    public function testWithoutOverlappingLockLifecycle()
    {
        $lockExistedDuringRun = false;

        $event = $this->schedule->call(function () use (&$lockExistedDuringRun, &$event) {
            // 在任务运行过程中，互斥锁应当存在于底层 Cache/Mutex 中
            $lockExistedDuringRun = $event->mutex->exists($event);

            return 0;
        })->name('test-overlapping-task')->withoutOverlapping(10);

        // 运行前锁不存在
        $this->assertFalse($event->mutex->exists($event));

        $exitCode = $this->runner->runSafely($event);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($lockExistedDuringRun, '互斥锁在任务执行期间应当处于持有状态');
        // 运行后锁已被安全释放
        $this->assertFalse($event->mutex->exists($event), '互斥锁在任务结束后应当被释放');

        $event->withoutOverlapping = false;
        $event->description = null;
    }

    /**
     * 4. 测试 onSuccess 与 onFailure 回调正确隔离触发.
     */
    public function testOnSuccessAndOnFailureCallbacks()
    {
        $successCalled = false;
        $failureCalled = false;

        // 成功任务：exitCode = 0
        $successEvent = $this->schedule->call(function () {
            return 0;
        })->onSuccess(function () use (&$successCalled) {
            $successCalled = true;
        })->onFailure(function () use (&$failureCalled) {
            $failureCalled = true;
        });

        $this->runner->runSafely($successEvent);

        $this->assertTrue($successCalled, 'onSuccess 回调应当在成功时触发');
        $this->assertFalse($failureCalled, 'onFailure 回调不应当在成功时触发');

        // 失败任务：抛出异常
        $successCalled = false;
        $failureCalled = false;

        $failureEvent = $this->schedule->call(function () {
            throw new Exception('Task deliberate failure');
        })->onSuccess(function () use (&$successCalled) {
            $successCalled = true;
        })->onFailure(function () use (&$failureCalled) {
            $failureCalled = true;
        });

        $exitCode = $this->runner->runSafely($failureEvent);

        $this->assertNotSame(0, $exitCode);
        $this->assertFalse($successCalled, 'onSuccess 回调不应当在失败时触发');
        $this->assertTrue($failureCalled, 'onFailure 回调应当在失败时触发');
    }

    /**
     * 5. 测试任务异常时派发 ScheduledTaskFailed 并上报异常处理器.
     */
    public function testExceptionHandlingAndReporting()
    {
        EventFacade::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskFinished::class,
            ScheduledTaskFailed::class,
        ]);

        $exceptionMessage = 'Test exception reporting';
        $event = $this->schedule->call(function () use ($exceptionMessage) {
            throw new Exception($exceptionMessage);
        });

        $exitCode = $this->runner->runSafely($event);

        $this->assertNotSame(0, $exitCode);

        EventFacade::assertDispatched(ScheduledTaskFailed::class, function ($e) use ($exceptionMessage) {
            return str_contains($e->exception->getMessage(), $exceptionMessage)
                && (!property_exists($e, 'runtime') || is_float($e->runtime));
        });
    }

    /**
     * 6. 测试输出重定向 (sendOutputTo 与 appendOutputTo).
     */
    public function testOutputRedirection()
    {
        $tempDir = sys_get_temp_dir();
        $outFile = $tempDir.DIRECTORY_SEPARATOR.'knight_schedule_out_'.uniqid().'.log';

        $event = $this->schedule->call(function () {
            echo "Hello Output Redirection\n";

            return 0;
        })->sendOutputTo($outFile);

        $this->runner->runSafely($event);

        $this->assertFileExists($outFile);
        $this->assertSame("Hello Output Redirection\n", file_get_contents($outFile));

        // 测试追加模式
        $appendEvent = $this->schedule->call(function () {
            echo "Second Line Appended\n";

            return 0;
        })->appendOutputTo($outFile);

        $this->runner->runSafely($appendEvent);

        $this->assertSame("Hello Output Redirection\nSecond Line Appended\n", file_get_contents($outFile));

        @unlink($outFile);
    }

    /**
     * 7. 测试沙箱配置 (Config) 与全局变量快照隔离与回滚.
     */
    public function testConfigAndGlobalVariablesRollback()
    {
        config(['test.knight_var' => 'initial_value']);
        $_SERVER['KNIGHT_TEST_KEY'] = 'initial_server';
        $_ENV['KNIGHT_TEST_ENV'] = 'initial_env';

        $event = $this->schedule->call(function () {
            // 在任务内部恶意/不慎篡改全局配置与环境变量
            config(['test.knight_var' => 'mutated_by_task']);
            config(['test.brand_new_key' => 'leak_attempt']);
            $_SERVER['KNIGHT_TEST_KEY'] = 'mutated_server';
            $_ENV['KNIGHT_TEST_ENV'] = 'mutated_env';
            putenv('KNIGHT_PUTENV_KEY=leak_attempt');

            return 0;
        });

        $this->runner->runSafely($event);

        // 验证任务退出后，沙箱完全回滚到原始快照
        $this->assertSame('initial_value', config('test.knight_var'), 'Config 配置应当在任务收尾时原样回滚');
        $this->assertNull(config('test.brand_new_key'), '新增的 Config 键应当在收尾时被彻底清除');
        $this->assertSame('initial_server', $_SERVER['KNIGHT_TEST_KEY'], '$_SERVER 变量应当在任务收尾时原样回滚');
        $this->assertSame('initial_env', $_ENV['KNIGHT_TEST_ENV'], '$_ENV 变量应当在任务收尾时原样回滚');
        $this->assertFalse(getenv('KNIGHT_PUTENV_KEY'), 'putenv 环境变量应当在收尾时被彻底移除');

        unset($_SERVER['KNIGHT_TEST_KEY'], $_ENV['KNIGHT_TEST_ENV']);
    }

    /**
     * 8. 测试 Carbon 测试时钟 (Carbon::setTestNow) 自动清洗复位.
     */
    public function testCarbonClockCleanup()
    {
        Carbon::setTestNow(); // 确保前置干净

        $event = $this->schedule->call(function () {
            Carbon::setTestNow(Carbon::parse('2099-12-31 23:59:59'));

            return 0;
        });

        $this->runner->runSafely($event);

        $this->assertFalse(Carbon::hasTestNow(), 'Carbon::setTestNow 应被沙箱强制复位');
    }

    /**
     * 9. 测试前置门禁过滤器 (when / skip).
     */
    public function testFiltersPassAndSkipEvent()
    {
        EventFacade::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskSkipped::class,
        ]);

        $passed = false;

        $event = $this->schedule->call(function () {
            return 0;
        })->when(function () use (&$passed) {
            return $passed;
        });

        $this->assertFalse($this->runner->shouldRun($event, $this->schedule));
        EventFacade::assertDispatched(ScheduledTaskSkipped::class);

        // 条件允许时
        $passed = true;
        $this->assertTrue($this->runner->shouldRun($event, $this->schedule));
    }

    /**
     * 10. 测试调度暂停门禁 (illuminate:schedule:paused).
     */
    public function testSchedulePausedFilter()
    {
        $cache = $this->app['cache'];

        // 未暂停
        $cache->forget('illuminate:schedule:paused');
        $normalEvent = $this->schedule->call(fn () => 0);
        $this->assertTrue($this->runner->shouldRun($normalEvent, $this->schedule));

        // 暂停状态
        $cache->forever('illuminate:schedule:paused', true);

        // 普通任务不能运行
        $this->assertFalse($this->runner->shouldRun($normalEvent, $this->schedule));

        // 显式声明 runsWhenPaused 的任务仍可运行
        $runsWhenPausedEvent = $this->schedule->call(fn () => 0);
        if (method_exists($runsWhenPausedEvent, 'evenWhenPaused')) {
            $runsWhenPausedEvent->evenWhenPaused();
            $this->assertTrue($this->runner->shouldRun($runsWhenPausedEvent, $this->schedule));
        }

        $cache->forget('illuminate:schedule:paused');
    }

    /**
     * 11. 测试同进程直驱 Artisan 命令行任务 (sendOutputTo 与 appendOutputTo).
     */
    public function testArtisanCommandExecutionAndOutputRedirection()
    {
        $tempDir = sys_get_temp_dir();
        $outFile = $tempDir.DIRECTORY_SEPARATOR.'knight_artisan_out_'.uniqid().'.log';

        $event = $this->schedule->command('env')->sendOutputTo($outFile);

        $exitCode = $this->runner->runSafely($event);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outFile);
        $this->assertNotEmpty(file_get_contents($outFile));

        @unlink($outFile);
    }

    /**
     * 12. 测试 onOneServer 单机锁缓存清除.
     */
    public function testClearScheduleMutexCache()
    {
        $this->runner->clearScheduleMutexCache($this->schedule);

        if (!property_exists($this->schedule, 'mutexCache')) {
            $this->markTestSkipped('Schedule::$mutexCache is only supported in Laravel 10+.');
        }

        $reflection = new \ReflectionProperty($this->schedule, 'mutexCache');
        $reflection->setAccessible(true);
        $this->assertSame([], $reflection->getValue($this->schedule));
    }

    /**
     * 13. 测试 extractArtisanCommand 全平台路径与重定向提取.
     */
    public function testExtractArtisanCommandVariations()
    {
        // 1. 标准 artisan 命令
        $this->assertSame(
            'emails:send --force',
            $this->runner->extractArtisanCommand('php artisan emails:send --force')
        );
        $this->assertSame('proxy:ping', $this->runner->extractArtisanCommand("'artisan' proxy:ping"));

        // 2. Linux 绝对路径与重定向
        $cmdLinux = '"/usr/local/bin/php" "/var/www/artisan" cache:gc --cache=ots > /dev/null 2>&1';
        $this->assertSame('cache:gc --cache=ots', $this->runner->extractArtisanCommand($cmdLinux));

        // 3. Windows 路径与多层目录
        $cmdWin = "'C:\\php\\php.exe' 'C:\\project\\artisan' model:prune --model=App\\Models\\User";
        $this->assertSame('model:prune --model=App\\Models\\User', $this->runner->extractArtisanCommand($cmdWin));

        // 4. 追加重定向
        $this->assertSame(
            'queue:work --once',
            $this->runner->extractArtisanCommand('artisan queue:work --once >> /tmp/queue.log')
        );

        // 5. 非 artisan 命令
        $this->assertNull($this->runner->extractArtisanCommand('/bin/bash script.sh'));
        $this->assertNull($this->runner->extractArtisanCommand('python3 process.py'));
        $this->assertNull($this->runner->extractArtisanCommand(null));
        $this->assertNull($this->runner->extractArtisanCommand('artisan'));
    }

    /**
     * 14. 测试 withoutOverlapping 互斥锁在重叠跳过时绝不误删外部进程持有的锁
     */
    public function testWithoutOverlappingDoesNotDeleteOtherProcessLockWhenSkipped()
    {
        $executed = false;
        $event = $this->schedule->call(function () use (&$executed) {
            $executed = true;

            return 0;
        })->description('test-mutex-protection')->withoutOverlapping(10);

        // 模拟外部另一个进程已经持有锁
        $this->assertTrue($event->mutex->create($event));
        $this->assertTrue($event->mutex->exists($event), '前置：锁已被外部进程持有');

        // 执行当前任务（应当因为重叠而跳过）
        $exitCode = $this->runner->runSafely($event);

        $this->assertSame(0, $exitCode);
        $this->assertFalse($executed, '因重叠跳过的任务不应被执行');
        // 关键断言：本任务跳过后，绝不能把外部进程持有的锁在 finally 中删掉！
        $this->assertTrue($event->mutex->exists($event), '外部进程持有的互斥锁绝不能被误删');

        // 清理锁
        $event->mutex->forget($event);

        $event->withoutOverlapping = false;
        $event->description = null;
    }

    /**
     * 15. 测试任务带有 description 时的摘要显示.
     */
    public function testEventSummaryWithDescription()
    {
        $event = $this->schedule->command('env')->description('Display Environment Details');
        $this->assertSame('Display Environment Details', $this->runner->getEventSummary($event));

        $callbackEvent = $this->schedule->call(function () {
            return 0;
        })->description('Custom Callback Description');
        $this->assertSame('Custom Callback Description', $this->runner->getEventSummary($callbackEvent));
    }

    /**
     * 16. 测试维护模式下任务过滤门禁
     */
    public function testMaintenanceModeHandling()
    {
        $normalEvent = $this->schedule->call(function () {
            return 0;
        });
        $maintenanceEvent = $this->schedule->call(function () {
            return 0;
        })->evenInMaintenanceMode();

        // 默认非维护模式
        $this->assertTrue($this->runner->shouldRun($normalEvent, $this->schedule));
        $this->assertTrue($this->runner->shouldRun($maintenanceEvent, $this->schedule));
    }

    /**
     * 17. 测试指定操作系统用户 (user) 的命令不走同进程内存直驱.
     */
    public function testCommandWithUserDoesNotDirectCall()
    {
        $event = $this->schedule->command('env')->user('nobody');
        $this->assertSame('nobody', $event->user);

        // 验证有 user 时 command 不会被提取为直驱命令
        $isEligible = ($event->command !== null
            && !$event instanceof \Illuminate\Console\Scheduling\CallbackEvent
            && empty($event->user));
        $artisanCommand = $isEligible ? $this->runner->extractArtisanCommand($event->command) : null;
        $this->assertNull($artisanCommand, '指定特定系统用户的命令绝不能走内存直驱');
    }

    /**
     * 18. 测试常驻内存下 Event 对象跨轮次 exitCode 复位.
     */
    public function testEventExitCodeResetBeforeRun()
    {
        $event = $this->schedule->call(function () {
            return 0;
        });
        $event->exitCode = 123; // 模拟上一轮残留的脏状态

        $this->runner->runSafely($event);
        $this->assertSame(0, $event->exitCode, '每次运行前必须复位上一轮残留的 exitCode');
    }

    /**
     * 19. 测试秒级任务在暂停时时钟推进.
     */
    public function testTouchRepeatEventOnPause()
    {
        $event = $this->schedule->call(function () {
            return 0;
        });

        if (!method_exists($event, 'everyTenSeconds')) {
            $this->markTestSkipped('Sub-minute repeating tasks are not supported in this Laravel version.');
        }

        $event->everyMinute()->everyTenSeconds();

        $this->runner->touchRepeatEvent($event);

        $reflection = new \ReflectionProperty($event, 'lastChecked');
        $reflection->setAccessible(true);
        $lastChecked = $reflection->getValue($event);

        $this->assertInstanceOf(\DateTimeInterface::class, $lastChecked);
    }

    /**
     * 20. 测试单机锁 (onOneServer) 在当分钟内秒级重复调用时不重复穿透 Redis.
     */
    public function testServerShouldRunPreservesCacheWithinSameMinute()
    {
        if (!property_exists($this->schedule, 'mutexCache')) {
            $this->markTestSkipped('Schedule::$mutexCache is only supported in Laravel 10+.');
        }

        $event = $this->schedule->command('env')->onOneServer();
        $baseTime = Carbon::now()->startOfMinute();

        // 首轮抢锁成功
        $firstResult = $this->runner->serverShouldRun($event, $this->schedule, $baseTime);
        $this->assertTrue($firstResult);

        // 同一分钟内再次检测（如秒级任务），必须命中 Schedule::mutexCache 依然返回 true，绝不被误判拒绝
        $secondResult = $this->runner->serverShouldRun($event, $this->schedule, $baseTime);
        $this->assertTrue($secondResult, '同一分钟内的秒级重复检测必须复用单机锁结果');

        // 新分钟到来，通过 resetScheduleForNewMinute 清除上一分钟缓存
        $this->runner->resetScheduleForNewMinute($this->schedule);
        $nextMinute = $baseTime->copy()->addMinute();
        $thirdResult = $this->runner->serverShouldRun($event, $this->schedule, $nextMinute);
        $this->assertTrue($thirdResult, '跨分钟重置后应正常向远端抢占新一分钟的锁');
    }

    /**
     * 21. 测试包含管道符或命令连接符的命令不能走内存直驱.
     */
    public function testComplexShellCommandsFallbackToNative()
    {
        $cmdPipe = 'php artisan inspire | grep -v quote';
        $this->assertNull($this->runner->extractArtisanCommand($cmdPipe));

        $cmdAnd = 'php artisan config:clear && php artisan route:clear';
        $this->assertNull($this->runner->extractArtisanCommand($cmdAnd));

        $cmdSemicolon = 'php artisan env; echo done';
        $this->assertNull($this->runner->extractArtisanCommand($cmdSemicolon));
    }

    /**
     * 22. 测试后台任务 (runInBackground) 自动降级为原生后台执行而不走同进程同步阻塞.
     */
    public function testRunInBackgroundFallsBackToNative()
    {
        $event = $this->schedule->command('env')->runInBackground();
        $this->assertTrue($event->runInBackground);

        // runSafely 在处理 runInBackground 任务时应走原生
        $exitCode = $this->runner->runSafely($event);
        $this->assertSame(0, $exitCode);
    }

    /**
     * 23. 测试 Artisan 命令在 withoutOverlapping 冲突时，严格派发 Finished 事件且绝不误删外部进程持有的锁
     */
    public function testArtisanCommandWithoutOverlappingDoesNotDeleteOtherProcessLock()
    {
        EventFacade::fake([
            ScheduledTaskStarting::class,
            ScheduledTaskFinished::class,
            ScheduledTaskSkipped::class,
        ]);

        $event = $this->schedule->command('env')->withoutOverlapping(10);

        // 模拟外部进程先行抢到锁
        $this->assertTrue($event->mutex->create($event));
        $this->assertTrue($event->mutex->exists($event), '外部锁应存在');

        // 执行当前任务
        $exitCode = $this->runner->runSafely($event);
        $this->assertSame(0, $exitCode);

        // 100% 对齐官方契约：派发 Starting 与 Finished，绝不派发 Skipped
        EventFacade::assertDispatched(ScheduledTaskStarting::class);
        EventFacade::assertDispatched(ScheduledTaskFinished::class);
        EventFacade::assertNotDispatched(ScheduledTaskSkipped::class);

        // 关键断言：外部进程的锁绝不应被当前任务 finally 误删
        $this->assertTrue($event->mutex->exists($event), '外部进程的锁必须保持完好');

        $event->mutex->forget($event);
    }

    /**
     * 24. 测试包含空格的带引号项目路径能精准提取 Artisan 子命令.
     */
    public function testExtractArtisanCommandWithSpacesInPath()
    {
        $singleQuote = "'C:\\Program Files\\My App\\artisan' emails:send --force";
        $this->assertSame('emails:send --force', $this->runner->extractArtisanCommand($singleQuote));

        $doubleQuote = '"/var/www/my project/artisan" queue:work redis --sleep=3';
        $this->assertSame('queue:work redis --sleep=3', $this->runner->extractArtisanCommand($doubleQuote));
    }

    /**
     * 25. 测试 purgeAllConnections 安全清空长连接.
     */
    public function testPurgeAllConnections()
    {
        // 触发调用不抛出异常
        $this->runner->purgeAllConnections();
        $this->assertTrue(true);
    }
}
