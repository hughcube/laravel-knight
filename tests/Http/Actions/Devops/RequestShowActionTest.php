<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\RequestShowAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestShowActionTest extends TestCase
{
    public function testActionReturnsRequestDetails()
    {
        $request = Request::create('/devops/request', 'GET', [], [], [], [
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->app->instance('request', $request);

        $action = $this->app->make(RequestShowAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('uri', $payload['Data']);
        $this->assertArrayHasKey('method', $payload['Data']);
        $this->assertArrayHasKey('client_ip', $payload['Data']);
        $this->assertArrayHasKey('headers', $payload['Data']);
    }

    public function testActionLogsWhenXLogParamGiven()
    {
        $request = Request::create('/devops/request', 'GET', ['x-log' => 1]);
        $this->app->instance('request', $request);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return false !== strpos($message, 'request: uri:');
            });

        $action = $this->app->make(RequestShowAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
    }
}
