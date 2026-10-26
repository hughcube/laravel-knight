<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Jobs\AutoCleanFileJob;
use HughCube\Laravel\Knight\Tests\TestCase;

class AutoCleanFileJobTest extends TestCase
{
    public function testDeletesExpiredFiles()
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight_autoclean_'.uniqid();
        mkdir($dir, 0777, true);

        $oldFile = $dir.DIRECTORY_SEPARATOR.'old.log';
        $newFile = $dir.DIRECTORY_SEPARATOR.'new.log';
        file_put_contents($oldFile, 'old');
        file_put_contents($newFile, 'new');

        touch($oldFile, time() - 2 * 86400);
        touch($newFile, time());

        try {
            $job = new AutoCleanFileJob([
                'dir' => $dir,
                'pattern' => '*.log',
                'max_days' => 1,
            ]);
            $job->handle();

            $this->assertFileDoesNotExist($oldFile);
            $this->assertFileExists($newFile);
        } finally {
            @unlink($oldFile);
            @unlink($newFile);
            @rmdir($dir);
        }
    }
}
