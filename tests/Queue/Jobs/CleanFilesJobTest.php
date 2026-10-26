<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Jobs\CleanFilesJob;
use HughCube\Laravel\Knight\Tests\TestCase;

class CleanFilesJobTest extends TestCase
{
    public function testRun()
    {
        $this->assertJob(CleanFilesJob::new([
            'items' => [
                [
                    'dir' => sys_get_temp_dir(),
                    'pattern' => '/^laravel.*-\d{4}-\d{2}-\d{2}\.log$/',
                    'max_days' => 30,
                ],
            ],
        ]));
    }
}
