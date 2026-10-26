<?php

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Testing\Traits\TestJobCase;
use HughCube\Laravel\Knight\Tests\TestCase;

class TestJobCaseTest extends TestCase
{
    use TestJobCase {
        testAction as traitTestAction;
    }

    private object $job;

    public function getJob(): object
    {
        if (! isset($this->job)) {
            $this->job = new class()
            {
                public ?string $logChannel = null;
                public bool $handled = false;

                public function setLogChannel(string $channel): void
                {
                    $this->logChannel = $channel;
                }

                public function handle(): void
                {
                    $this->handled = true;
                }
            };
        }

        return $this->job;
    }

    public function testAction()
    {
        $this->traitTestAction();

        $job = $this->getJob();
        $this->assertSame('stdout', $job->logChannel);
        $this->assertTrue($job->handled);
    }
}
