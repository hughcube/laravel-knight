<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Queue\Jobs\RotateFileJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class LogsRotateCommandTest extends TestCase
{
    public function testHandleFullRotate()
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'knight_rotate_test_' . uniqid();
        File::ensureDirectoryExists($tempDir);

        $file1 = $tempDir . DIRECTORY_SEPARATOR . 'test-rotate-1.log';
        $file2 = $tempDir . DIRECTORY_SEPARATOR . 'test-rotate-2.log';

        File::put($file1, "hello world content\n");
        File::put($file2, ''); // empty file

        try {
            $this->artisan('devops:logs:rotate', [
                '--dir' => [$tempDir],
                '--pattern' => ['test-rotate-*.log'],
                '--date-format' => 'Y-m-d',
            ])->assertSuccessful();

            $dateSuffix = Carbon::now()->format('Y-m-d');
            $rotatedFile1 = $tempDir . DIRECTORY_SEPARATOR . "test-rotate-1-{$dateSuffix}.log";

            $this->assertFileExists($rotatedFile1);
            $this->assertSame("hello world content\n", file_get_contents($rotatedFile1));
            $this->assertSame('', file_get_contents($file1)); // file1 truncated

            // empty file was skipped by default
            $rotatedFile2 = $tempDir . DIRECTORY_SEPARATOR . "test-rotate-2-{$dateSuffix}.log";
            $this->assertFileDoesNotExist($rotatedFile2);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function testHandleTrimRotate()
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'knight_trim_rotate_test_' . uniqid();
        File::ensureDirectoryExists($tempDir);

        $logFile = $tempDir . DIRECTORY_SEPARATOR . 'app.log';

        // 生成多行内容，每行 100 字节
        $lines = [];
        for ($i = 1; $i <= 100; $i++) {
            $lines[] = sprintf("line-%04d: %s\n", $i, str_repeat('X', 80));
        }
        $content = implode('', $lines);
        File::put($logFile, $content);
        $originalSize = strlen($content);

        try {
            // max-size=4000B, keep-size=2000B
            $this->artisan('devops:logs:rotate', [
                '--dir' => [$tempDir],
                '--pattern' => ['app.log'],
                '--max-size' => '4000B',
                '--keep-size' => '2000B',
                '--date-format' => 'YmdHis',
            ])->assertSuccessful();

            clearstatcache(true, $logFile);
            $newSize = filesize($logFile);
            $this->assertLessThan($originalSize, $newSize);
            $this->assertGreaterThan(0, $newSize);

            // 验证保留段仍然以合法换行开头，且内容是最新末尾行
            $newContent = file_get_contents($logFile);
            $this->assertStringContainsString('line-0100:', $newContent);
            $this->assertStringNotContainsString('line-0001:', $newContent);

            // 验证归档文件生成且包含最老段
            $archives = File::glob($tempDir . DIRECTORY_SEPARATOR . 'app-*.log');
            $this->assertNotEmpty($archives);
            $archiveContent = file_get_contents($archives[0]);
            $this->assertStringContainsString('line-0001:', $archiveContent);
            $this->assertStringNotContainsString('line-0100:', $archiveContent);
            $this->assertStringEndsWith("\n", $archiveContent); // 绝不碎行
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
