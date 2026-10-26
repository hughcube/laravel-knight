<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\QueueDepthAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class QueueDepthActionTest extends TestCase
{
    public function testActionReturnsQueueDepth()
    {
        $queueMock = \Mockery::mock(QueueContract::class);
        $queueMock->shouldReceive('size')->with('default')->andReturn(5);
        $queueMock->shouldReceive('size')->with('high')->andReturn(2);

        Queue::shouldReceive('connection')->with('redis')->andReturn($queueMock);

        $request = Request::create('/devops/queue-depth', 'GET', [
            'x-connection' => 'redis',
            'x-queues' => 'default, high',
        ]);
        $this->app->instance('request', $request);

        $action = $this->app->make(QueueDepthAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertSame(7, $payload['Data']['value']);
        $this->assertSame(['default' => 5, 'high' => 2], $payload['Data']['queues']);
    }
}
