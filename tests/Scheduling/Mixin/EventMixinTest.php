<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling\Mixin;

use Closure;
use HughCube\Laravel\Knight\Scheduling\Mixin\EventMixin;
use HughCube\Laravel\Knight\Tests\TestCase;

class EventMixinTest extends TestCase
{
    public function testSendOutputToDynamicRegistersBeforeCallback(): void
    {
        $event = $this->newFakeEvent();
        $mixin = new EventMixin();

        $macro = $mixin->sendOutputToDynamic();
        $boundMacro = $macro->bindTo($event, get_class($event));

        $result = $boundMacro(function () {
            return '/tmp/knight-event.log';
        }, false);

        $this->assertSame($event, $result);
        $this->assertInstanceOf(Closure::class, $event->beforeCallback);

        $callback = $event->beforeCallback;
        $callback();

        $this->assertSame('/tmp/knight-event.log', $event->outputPath);
        $this->assertFalse($event->appendMode);
    }

    public function testSendOutputToDynamicUsesAppendDefaultValue(): void
    {
        $event = $this->newFakeEvent();
        $mixin = new EventMixin();

        $macro = $mixin->sendOutputToDynamic();
        $boundMacro = $macro->bindTo($event, get_class($event));

        $boundMacro(function () {
            return '/tmp/knight-default.log';
        });

        $callback = $event->beforeCallback;
        $callback();

        $this->assertSame('/tmp/knight-default.log', $event->outputPath);
        $this->assertTrue($event->appendMode);
    }

    private function newFakeEvent()
    {
        return new class() {
            public $beforeCallback;
            public $outputPath;
            public $appendMode;

            public function before(callable $callback)
            {
                $this->beforeCallback = $callback;

                return $this;
            }

            public function sendOutputTo($path, $append = true)
            {
                $this->outputPath = $path;
                $this->appendMode = $append;

                return $this;
            }
        };
    }
}
