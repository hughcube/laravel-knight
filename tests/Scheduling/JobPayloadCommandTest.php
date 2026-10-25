<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\Jobs\ScheduleJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class JobPayloadCommandTest extends TestCase
{
    public function testDefaultJobIsScheduleJob()
    {
        $exitCode = Artisan::call('queue:job-payload');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('job HughCube\Laravel\Knight\Scheduling\Jobs\ScheduleJob payload:', $output);

        // 提取 payload JSON
        $lines = array_filter(explode("\n", trim($output)));
        $payloadJson = end($lines);

        $payload = json_decode($payloadJson, true);
        $this->assertIsArray($payload);
        $this->assertSame('Illuminate\Queue\CallQueuedHandler@call', $payload['job']);
        $this->assertSame(ScheduleJob::class, $payload['data']['commandName']);

        // 反序列化还原验证
        $command = unserialize($payload['data']['command']);
        $this->assertInstanceOf(ScheduleJob::class, $command);
    }

    public function testWithCustomJobAndData()
    {
        $exitCode = Artisan::call('queue:job-payload', [
            'job'    => ScheduleJob::class,
            '--data' => json_encode(['custom_key' => 'custom_val']),
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();

        $lines = array_filter(explode("\n", trim($output)));
        $payloadJson = end($lines);
        $payload = json_decode($payloadJson, true);

        /** @var ScheduleJob $command */
        $command = unserialize($payload['data']['command']);
        $this->assertInstanceOf(ScheduleJob::class, $command);
        $this->assertSame(['custom_key' => 'custom_val'], $command->getData());
    }

    public function testNonExistentJobThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        Artisan::call('queue:job-payload', [
            'job' => 'Non\\Existent\\Job\\Class',
        ]);
    }

    /**
     * 测试通过别名 schedule:job-payload 也能成功执行.
     */
    public function testAliasScheduleJobPayloadWorks()
    {
        $exitCode = Artisan::call('schedule:job-payload');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('job HughCube\Laravel\Knight\Scheduling\Jobs\ScheduleJob payload:', $output);
    }
}
