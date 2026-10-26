<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\VersionAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class VersionActionTest extends TestCase
{
    public function testActionReturnsDefaultVersion()
    {
        $action = new VersionAction();
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame('Success', $payload['Code']);
        $this->assertArrayHasKey('build_version', $payload['Data']);
        $this->assertArrayHasKey('build_time', $payload['Data']);
    }
}
