<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\StatusAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatusActionTest extends TestCase
{
    public function testActionReturnsJson()
    {
        $request = Request::create('/devops/status', 'GET', ['x-format' => 'json']);
        $this->app->instance('request', $request);

        $action = $this->app->make(StatusAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('healthy', $payload['Data']);
        $this->assertArrayHasKey('checks', $payload['Data']);
        $this->assertArrayHasKey('Database', $payload['Data']['checks']);
        $this->assertArrayHasKey('Cache', $payload['Data']['checks']);
    }

    public function testActionReturnsHtml()
    {
        $request = Request::create('/devops/status', 'GET');
        $this->app->instance('request', $request);

        $action = $this->app->make(StatusAction::class);
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
    }
}
