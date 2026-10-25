<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Commands\DaemonCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

class DaemonCommandTest extends TestCase
{
    /**
     * 测试命令签名与选项完整性.
     */
    public function testCommandSignature()
    {
        /** @var DaemonCommand $command */
        $command = $this->app->make(DaemonCommand::class);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('max-memory'));
        $this->assertTrue($definition->hasOption('max-runs'));
        $this->assertTrue($definition->hasOption('interval'));
        $this->assertTrue($definition->hasOption('immediate'));
        $this->assertTrue($definition->hasOption('whisper'));
        $this->assertTrue($definition->hasOption('run-output-file'));
    }

    /**
     * 测试守护进程单轮优雅执行并安全退出.
     */
    public function testDaemonRunsSingleCycleAndExits()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;

            return 0;
        })->everyMinute();

        $exitCode = Artisan::call('schedule:daemon', [
            '--max-runs'  => 1,
            '--immediate' => true,
            '--interval'  => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($executed);
    }

    /**
     * 测试内存阈值超限触发平滑退出.
     */
    public function testDaemonMemoryLimitExceededExits()
    {
        $exitCode = Artisan::call('schedule:daemon', [
            '--max-memory' => 1, // 1MB 必然触发内存超限
            '--immediate'  => true,
            '--interval'   => 10,
        ]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * 测试 --run-output-file 选项将控制台输出正确重定向写入日志文件.
     */
    public function testRunOutputFileRedirection()
    {
        $logFile = sys_get_temp_dir().'/schedule_daemon_test_'.uniqid().'.log';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        $exitCode = Artisan::call('schedule:daemon', [
            '--immediate'       => true,
            '--max-runs'        => 1,
            '--whisper'         => false,
            '--run-output-file' => $logFile,
            '--interval'        => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('Schedule daemon successfully stopped.', $content);

        @unlink($logFile);
    }

    /**
     * 测试 --whisper 选项静默模式.
     */
    public function testWhisperOption()
    {
        $exitCode = Artisan::call('schedule:daemon', [
            '--immediate' => true,
            '--max-runs'  => 1,
            '--whisper'   => true,
            '--interval'  => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringNotContainsString('No scheduled commands are ready to run.', $output);
    }

    /**
     * 测试手动调用 stop() 方法平滑停止守护进程.
     */
    public function testDaemonStopMethod()
    {
        /** @var DaemonCommand $command */
        $command = $this->app->make(DaemonCommand::class);

        $this->assertFalse($command->shouldQuit);
        $command->stop();
        $this->assertTrue($command->shouldQuit);
    }
}
