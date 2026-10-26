<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Devops;

use HughCube\Laravel\Knight\Http\Actions\Devops\PhpInfoAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class PhpInfoActionTest extends TestCase
{
    public function testActionReturnsHtml()
    {
        $action = new PhpInfoAction();
        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('phpinfo()', $response->getContent());
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
    }
}
