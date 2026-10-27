<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\File;

class LogsPruneCommandTest extends TestCase
{
    public function testHandle()
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'knight_prune_test_' . uniqid();
        File::ensureDirectoryExists($tempDir);

        $oldFile = $tempDir . DIRECTORY_SEPARATOR . 'old.log';
        $newFile = $tempDir . DIRECTORY_SEPARATOR . 'new.log';

        File::put($oldFile, 'old content');
        File::put($newFile, 'new content');

        // Set old file mtime to 40 days ago
        touch($oldFile, time() - (40 * 86400));

        try {
            $this->artisan('devops:logs:prune', [
                '--dir' => [$tempDir],
                '--pattern' => ['*.log'],
                '--days' => 30,
            ])->assertSuccessful();

            $this->assertFileDoesNotExist($oldFile);
            $this->assertFileExists($newFile);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
