<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/26
 * Time: 20:21.
 */

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\KRTest;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

class KRTestTest extends TestCase
{
    public function testRun()
    {
        $this->artisan('krtest', ['--class' => KR::class])->assertExitCode(0);
    }

    public function testMakeInstanceThrowsWhenClassMissing()
    {
        $command = new class() extends KRTest
        {
            public function option($key = null)
            {
                return null;
            }
        };

        $command->setLaravel($this->app);

        $this->expectException(RuntimeException::class);
        $this->callMethod($command, 'makeInstance');
    }

    public function testMakeInstanceThrowsWhenClassNotFound()
    {
        $command = new class() extends KRTest
        {
            public function option($key = null)
            {
                return 'MissingClass';
            }
        };

        $command->setLaravel($this->app);

        $this->expectException(RuntimeException::class);
        $this->callMethod($command, 'makeInstance');
    }

    public function testFormatMemoryConvertsBytesToMegabytes()
    {
        $command = new KRTest();

        $memory = $this->callMethod($command, 'formatMemory', [1048576]);

        $this->assertSame(1.0, $memory);
    }
}
