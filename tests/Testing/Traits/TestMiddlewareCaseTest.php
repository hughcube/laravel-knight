<?php

namespace HughCube\Laravel\Knight\Tests\Testing\Traits;

use HughCube\Laravel\Knight\Testing\Traits\TestMiddlewareCase;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TestMiddlewareCaseTest extends TestCase
{
    use TestMiddlewareCase;

    public function testCreateTestRequest()
    {
        $request = $this->createMiddlewareTestRequest('POST', '/api/test', ['X-Custom' => 'value']);

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame('POST', $request->method());
        $this->assertSame('value', $request->headers->get('X-Custom'));
    }

    public function testCreateTestRequestDefaults()
    {
        $request = $this->createMiddlewareTestRequest();

        $this->assertSame('GET', $request->method());
        $this->assertSame('/', $request->getPathInfo());
    }

    public function testAssertMiddlewarePasses()
    {
        $middleware = new class()
        {
            public function handle($request, $next)
            {
                return $next($request);
            }
        };

        $response = $this->assertMiddlewarePasses($middleware);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->getContent());
    }

    public function testAssertMiddlewareBlocks()
    {
        $middleware = new class()
        {
            public function handle($request, $next)
            {
                throw new AccessDeniedHttpException('Denied');
            }
        };

        $this->assertMiddlewareBlocks($middleware, AccessDeniedHttpException::class);
    }

    public function testAssertMiddlewarePassesWithCustomRequest()
    {
        $middleware = new class()
        {
            public function handle($request, $next)
            {
                return $next($request);
            }
        };

        $request = $this->createMiddlewareTestRequest('POST', '/custom');
        $response = $this->assertMiddlewarePasses($middleware, $request);

        $this->assertSame('ok', $response->getContent());
    }
}
