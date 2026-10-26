<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\NowAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class NowActionTest extends TestCase
{
    public function testActionReturnsTimestampAndRfc3339()
    {
        $action = new NowAction();
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);

        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('timestamp', $payload['Data']);
        $this->assertArrayHasKey('rfc3339', $payload['Data']);
    }
}
