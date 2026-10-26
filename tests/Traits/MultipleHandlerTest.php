<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/30
 * Time: 17:36.
 */

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\Laravel\Knight\Traits\MultipleHandler;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Throwable;

class MultipleHandlerTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMultipleHandler()
    {
        $job = new class()
        {
            use MultipleHandler;

            public $doneHandlers = [];

            protected function aHandler10000()
            {
                $this->doneHandlers[] = __FUNCTION__;
            }

            protected function bHandler()
            {
                $this->doneHandlers[] = __FUNCTION__;
            }

            protected function cHandler100()
            {
                $this->doneHandlers[] = __FUNCTION__;
            }

            protected function dHandlerA()
            {
                $this->doneHandlers[] = __FUNCTION__;
            }
        };

        $this->assertSame(
            Collection::make($this->callMethod($job, 'getMultipleHandlers'))
                ->map(function (ReflectionMethod $method) {
                    return $method->name;
                })
                ->values()
                ->toArray(),
            ['bHandler', 'cHandler100', 'aHandler10000']
        );

        $this->assertEmpty($this->getProperty($job, 'doneHandlers'));
        $this->callMethod($job, 'triggerMultipleHandlers');
        $this->assertSame($this->getProperty($job, 'doneHandlers'), ['bHandler', 'cHandler100', 'aHandler10000']);
    }

    public function testTriggerMultipleHandlersStopsAndSkips()
    {
        $job = new class()
        {
            use MultipleHandler;

            public array $called = [];

            protected function bHandler()
            {
                $this->called[] = 'bHandler';

                return 'stop';
            }

            protected function aHandler10()
            {
                $this->called[] = 'aHandler10';
            }

            protected function skipHandler()
            {
                $this->called[] = 'skipHandler';
            }

            protected function isSkipMultipleHandlerMethod(ReflectionMethod $method): bool
            {
                return 'skipHandler' === $method->name;
            }

            protected function isStopMultipleHandlerResult($result, ?Throwable $exception = null): bool
            {
                return 'stop' === $result;
            }
        };

        $this->callMethod($job, 'triggerMultipleHandlers');

        $this->assertSame(['bHandler'], $job->called);
    }

    public function testTriggerMultipleHandlersReportsExceptions()
    {
        $job = new class()
        {
            use MultipleHandler;

            public array $called = [];
            public array $reported = [];

            protected function aHandler()
            {
                $this->called[] = 'aHandler';

                throw new \RuntimeException('boom');
            }

            protected function bHandler10()
            {
                $this->called[] = 'bHandler10';
            }

            protected function reportMultipleHandlerException(Throwable $exception): void
            {
                $this->reported[] = $exception->getMessage();
            }
        };

        $this->callMethod($job, 'triggerMultipleHandlers');

        $this->assertSame(['aHandler', 'bHandler10'], $job->called);
        $this->assertSame(['boom'], $job->reported);
    }

    public function testTriggerMultipleHandlersThrowsWhenTryExceptionFalse()
    {
        $job = new class()
        {
            use MultipleHandler;

            protected function aHandler()
            {
                throw new \RuntimeException('boom');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->callMethod($job, 'triggerMultipleHandlers', [false]);
    }
}
