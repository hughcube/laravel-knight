<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Jobs\RotateFileJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\File;

class RotateFileJobTest extends TestCase
{
    public function testSkipAlreadyRotatedFiles()
    {
        $tempDir = sys_get_temp_dir();
        $baseName = 'SkipRotateTest-' . md5(uniqid());
        $activeFile = sprintf('%s/%s.log', $tempDir, $baseName);
        $alreadyRotatedFile = sprintf('%s/%s-2026-10-27.log', $tempDir, $baseName);

        File::put($activeFile, "active content\n");
        File::put($alreadyRotatedFile, "old content\n");

        try {
            $result = RotateFileJob::rotateFiles(
                $tempDir,
                $baseName . '*.log',
                [],
                'Y-m-d'
            );

            $this->assertSame(1, $result['rotated']);
            $this->assertSame(realpath($activeFile), $result['items'][0]['path']);
            // 确保旧归档文件未被二次追加生成
            $this->assertFileDoesNotExist(sprintf('%s/%s-2026-10-27-2026-10-27.log', $tempDir, $baseName));
        } finally {
            File::delete($activeFile);
            File::delete($alreadyRotatedFile);
            File::delete(sprintf('%s/%s-%s.log', $tempDir, $baseName, date('Y-m-d')));
        }
    }

    public function testRun()
    {
        $tempDir = sys_get_temp_dir();

        $file = sprintf('%s/FileRotateJobTest-%s.test.log', $tempDir, md5(1));
        File::put($file, 'test');

        $file = sprintf('%s/FileRotateJobTest-%s.test.log', $tempDir, md5(2));
        File::put($file, '');

        try {
            $this->assertJob(RotateFileJob::new([
                'items' => [
                    [
                        'dir' => [
                            sprintf('%s/', $tempDir),
                            sprintf('%s/xxx/', $tempDir),
                        ],
                        'pattern' => 'FileRotateJobTest-*.test.log',
                        'date_format' => date('Y-m-d'),
                    ],
                ],
            ]));
        } finally {
            //File::delete($file);
        }
    }
}
