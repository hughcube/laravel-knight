<?php

namespace HughCube\Laravel\Knight\Testing\Traits;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait TestMiddlewareCase
{
    /**
     * 快速创建 Request.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $headers
     * @param  array  $server
     * @param  string  $content
     * @return Request
     */
    protected function createMiddlewareTestRequest(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        array $server = [],
        string $content = ''
    ): Request {
        $request = Request::create($uri, $method, [], [], [], $server, $content);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    /**
     * 断言中间件通过.
     *
     * @param  object  $middleware
     * @param  Request|null  $request
     * @return Response
     */
    protected function assertMiddlewarePasses($middleware, ?Request $request = null): Response
    {
        $request = $request ?: $this->createMiddlewareTestRequest();

        $response = $middleware->handle($request, function () {
            return new Response('ok', 200);
        });

        $this->assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * 断言中间件拒绝（抛出指定异常）.
     *
     * @param  object  $middleware
     * @param  string  $exceptionClass
     * @param  Request|null  $request
     * @return void
     */
    protected function assertMiddlewareBlocks($middleware, string $exceptionClass, ?Request $request = null): void
    {
        $request = $request ?: $this->createMiddlewareTestRequest();

        $this->expectException($exceptionClass);

        $middleware->handle($request, function () {
            return new Response('ok', 200);
        });
    }
}
