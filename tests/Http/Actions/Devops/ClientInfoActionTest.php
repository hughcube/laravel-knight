<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\ClientInfoAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientInfoActionTest extends TestCase
{
    public function testActionReturnsJson()
    {
        $request = Request::create('/devops/client-info', 'GET', ['x-format' => 'json'], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'REMOTE_ADDR' => '192.168.1.100',
        ]);
        $this->app->instance('request', $request);

        $action = $this->app->make(ClientInfoAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('网络与请求', $payload['Data']);
        $this->assertArrayHasKey('客户端类型识别', $payload['Data']);
        $this->assertArrayHasKey('操作系统与内核', $payload['Data']);
        $this->assertArrayHasKey('浏览器与应用', $payload['Data']);
        $this->assertSame('Windows', $payload['Data']['操作系统与内核']['操作系统']);
        $this->assertSame('10 / 11', $payload['Data']['操作系统与内核']['系统版本']);
    }

    public function testActionReturnsHtml()
    {
        $request = Request::create('/devops/client-info', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'MicroMessenger/8.0.30',
        ]);
        $this->app->instance('request', $request);

        $action = $this->app->make(ClientInfoAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
    }
}
