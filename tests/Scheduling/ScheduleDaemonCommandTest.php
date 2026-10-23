<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\ScheduleDaemonCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ScheduleDaemonCommandTest extends TestCase
{
    /**
     * 测试命令签名与选项完整性
     */
    public function testCommandSignature()
    {
        /** @var ScheduleDaemonCommand $command */
        $command = $this->app->make(ScheduleDaemonCommand::class);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('driver'));
        $this->assertTrue($definition->hasOption('concurrency'));
        $this->assertTrue($definition->hasOption('timeout'));
        $this->assertTrue($definition->hasOption('max-memory'));
        $this->assertTrue($definition->hasOption('max-runs'));
        $this->assertTrue($definition->hasOption('interval'));
        $this->assertTrue($definition->hasOption('immediate'));
        $this->assertTrue($definition->hasOption('whisper'));
        $this->assertTrue($definition->hasOption('run-output-file'));
    }

    /**
     * 测试环境未安装 Swoole 时，强制指定 --driver=swoole 抛出明确异常
     */
    public function testDriverResolutionThrowsWhenSwooleMissing()
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is installed in current PHP runtime.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Swoole extension is not loaded');

        Artisan::call('schedule:daemon', [
            '--driver' => 'swoole',
        ]);
    }

    /**
     * 测试原生驱动 (NativeDriver) 单轮优雅执行并安全退出
     */
    public function testNativeDriverRunsSingleCycleAndExits()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;
            return 0;
        })->everyMinute();

        $exitCode = Artisan::call('schedule:daemon', [
            '--driver'    => 'native',
            '--max-runs'  => 1,
            '--immediate' => true,
            '--interval'  => 50,
        ]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * 测试 auto 模式在无 Swoole 环境下自动降级为 Native 驱动安全运行
     */
    public function testAutoDriverRunsSafely()
    {
        $exitCode = Artisan::call('schedule:daemon', [
            '--driver'    => 'auto',
            '--max-runs'  => 1,
            '--immediate' => true,
            '--interval'  => 50,
        ]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * 测试 NativeDriver 内存阈值平滑退出
     */
    public function testNativeDriverMemoryThresholdExit()
    {
        $exitCode = Artisan::call('schedule:daemon', [
            '--driver'     => 'native',
            '--max-memory' => 1, // 1MB 必然触发内存超限
            '--immediate'  => true,
            '--interval'   => 50,
        ]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * 测试 SwooleDriver 驱动单轮执行与平滑退出
     */
    public function testSwooleDriverRunsSingleCycleAndExits()
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is not installed in current PHP runtime.');
        }

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;
            return 0;
        })->everyMinute();

        $exitCode = Artisan::call('schedule:daemon', [
            '--driver'      => 'swoole',
            '--max-runs'    => 1,
            '--immediate'   => true,
            '--concurrency' => 2,
            '--timeout'     => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($executed, 'Swoole 驱动下到期任务应当被成功执行');
    }

    /**
     * 测试 SwooleDriver 多任务受控并发执行
     */
    public function testSwooleDriverConcurrentTasks()
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole is not installed in current PHP runtime.');
        }

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $task1Executed = false;
        $task2Executed = false;

        $schedule->call(function () use (&$task1Executed) {
            \Swoole\Coroutine::sleep(0.05);
            $task1Executed = true;
            return 0;
        })->everyMinute();

        $schedule->call(function () use (&$task2Executed) {
            \Swoole\Coroutine::sleep(0.05);
            $task2Executed = true;
            return 0;
        })->everyMinute();

        $exitCode = Artisan::call('schedule:daemon', [
            '--driver'      => 'swoole',
            '--max-runs'    => 1,
            '--immediate'   => true,
            '--concurrency' => 4,
            '--timeout'     => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($task1Executed, 'Task 1 应当被执行');
        $this->assertTrue($task2Executed, 'Task 2 应当被执行');
    }

    /**
     * 测试信号注册在不同驱动下的分支绑定逻辑
     */
    public function testSignalHandlerRegistrationBranching()
    {
        /** @var ScheduleDaemonCommand $command */
        $command = $this->app->make(ScheduleDaemonCommand::class);

        $reflection = new \ReflectionMethod($command, 'registerSignalHandler');
        $reflection->setAccessible(true);

        $output = new \Symfony\Component\Console\Output\NullOutput();
        $driver = new \HughCube\Laravel\Knight\Scheduling\NativeDriver($output);

        // 测试针对 native 驱动的信号注册不抛出异常
        $reflection->invoke($command, $driver, 'native');

        $this->assertFalse($driver->isStopped());
    }

    /**
     * 测试 --run-output-file 选项将控制台输出正确重定向写入日志文件
     */
    public function testRunOutputFileRedirection()
    {
        $logFile = sys_get_temp_dir() . '/schedule_daemon_test_' . uniqid() . '.log';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        $this->artisan('schedule:daemon', [
            '--driver'    => 'native',
            '--immediate' => true,
            '--max-runs'  => 1,
            '--whisper'   => false,
            '--run-output-file' => $logFile,
        ])->assertExitCode(0)->run();
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('Schedule daemon (native) successfully stopped.', $content);

        @unlink($logFile);
    }
}
