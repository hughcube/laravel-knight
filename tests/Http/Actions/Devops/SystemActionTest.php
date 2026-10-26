<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\SystemAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class SystemActionTest extends TestCase
{
    public function testActionReturnsSystemDetails()
    {
        $action = new SystemAction();
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('os', $payload['Data']);
        $this->assertArrayHasKey('php_version', $payload['Data']);
        $this->assertArrayHasKey('current_memory_usage', $payload['Data']);
        $this->assertArrayHasKey('peak_memory_usage', $payload['Data']);
        $this->assertArrayHasKey('php_extensions', $payload['Data']);
    }
}
