<?php

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\Laravel\Knight\Traits\GetDispatcher;
use HughCube\Laravel\Knight\Traits\GetRequest;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;

class GetRequestDispatcherTraitTest extends TestCase
{
    public function testGetDispatcherUsesContainerWhenAvailable()
    {
        $events = new EventsDispatcher($this->app);
        $container = new class($events)
        {
            private $events;

            public function __construct($events)
            {
                $this->events = $events;
            }

            public function make($abstract)
            {
                return $this->events;
            }
        };

        $instance = new class($container)
        {
            use GetDispatcher;

            private $container;

            public function __construct($container)
            {
                $this->container = $container;
            }

            public function getContainer()
            {
                return $this->container;
            }
        };

        $this->assertSame($events, self::callMethod($instance, 'getDispatcher'));
    }

    public function testGetDispatcherFallsBackToApp()
    {
        $instance = new class()
        {
            use GetDispatcher;
        };

        $dispatcher = self::callMethod($instance, 'getDispatcher');
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
    }

    public function testGetRequestUsesContainerWhenAvailable()
    {
        $request = Request::create('/test', 'GET');
        $container = new class($request)
        {
            private $request;

            public function __construct($request)
            {
                $this->request = $request;
            }

            public function make($abstract)
            {
                return $this->request;
            }
        };

        $instance = new class($container)
        {
            use GetRequest;

            private $container;

            public function __construct($container)
            {
                $this->container = $container;
            }

            public function getContainer()
            {
                return $this->container;
            }
        };

        $this->assertSame($request, $instance->getRequest());
    }

    public function testGetRequestFallsBackToApp()
    {
        $instance = new class()
        {
            use GetRequest;
        };

        $this->assertSame($this->app->make('request'), $instance->getRequest());
    }
}
