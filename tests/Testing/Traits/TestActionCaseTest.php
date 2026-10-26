<?php

namespace HughCube\Laravel\Knight\Tests\Testing\Traits;

use HughCube\Laravel\Knight\Testing\Traits\TestActionCase;
use HughCube\Laravel\Knight\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

class TestActionCaseTest extends TestCase
{
    use TestActionCase;

    public function testAssertKnightHandleCallsHandleAndSetsLogChannel(): void
    {
        $job = new class()
        {
            public $handled = false;
            public $channel;

            public function setLogChannel($channel): void
            {
                $this->channel = $channel;
            }

            public function handle(): void
            {
                $this->handled = true;
            }
        };

        $this->assertKnightHandle($job);

        $this->assertTrue($job->handled);
        $this->assertSame('stdout', $job->channel);
    }

    public function testAssertKnightHandleFailsWhenHandleMethodMissing(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertKnightHandle(new \stdClass());
    }

    public function testAssertKnightHandleFailsWhenHandleThrowsException(): void
    {
        $job = new class()
        {
            public function handle(): void
            {
                throw new RuntimeException('job failed');
            }
        };

        try {
            $this->assertKnightHandle($job);
            $this->fail('Expected assertion failure was not thrown.');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('job failed', $e->getMessage());
        }
    }
}
