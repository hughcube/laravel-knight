<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\DisableResponseCache;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableResponseCacheTest extends TestCase
{
    public function testHandle()
    {
        $middleware = new DisableResponseCache();
        $request = Request::create('/test');

        $response = $middleware->handle($request, function () {
            return new Response('ok', 200);
        });

        $this->assertSame('max-age=0, must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
        $this->assertSame('*', $response->headers->get('Vary'));
    }
}
