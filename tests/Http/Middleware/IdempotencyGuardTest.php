<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\IdempotencyGuard;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyGuardTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache', [
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => 'array',
                    'serialize' => true,
                ],
            ],
        ]);
    }

    public function testPostWithIdempotencyKeyCachesResponse()
    {
        $middleware = new IdempotencyGuard();
        $request = Request::create('/api/orders', 'POST');
        $request->headers->set('X-Idempotency-Key', 'unique-key-123');

        $callCount = 0;
        $handler = function () use (&$callCount) {
            $callCount++;

            return new Response('created', 201);
        };

        // 第一次请求
        $response = $middleware->handle($request, $handler);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created', $response->getContent());
        $this->assertNull($response->headers->get('X-Idempotent-Replayed'));
        $this->assertSame(1, $callCount);

        // 第二次请求（重复）
        $response2 = $middleware->handle($request, $handler);
        $this->assertSame(201, $response2->getStatusCode());
        $this->assertSame('created', $response2->getContent());
        $this->assertSame('true', $response2->headers->get('X-Idempotent-Replayed'));
        $this->assertSame(1, $callCount); // handler没有再被调用
    }

    public function testGetRequestPassesThrough()
    {
        $middleware = new IdempotencyGuard();
        $request = Request::create('/api/orders', 'GET');
        $request->headers->set('X-Idempotency-Key', 'get-key-123');

        $response = $middleware->handle($request, function () {
            return new Response('list', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('list', $response->getContent());
    }

    public function testPostWithoutIdempotencyKeyPassesThrough()
    {
        $middleware = new IdempotencyGuard();
        $request = Request::create('/api/orders', 'POST');

        $callCount = 0;
        $handler = function () use (&$callCount) {
            $callCount++;

            return new Response('created', 201);
        };

        $middleware->handle($request, $handler);
        $middleware->handle($request, $handler);

        $this->assertSame(2, $callCount);
    }

    public function testPutRequestWithIdempotencyKey()
    {
        $middleware = new IdempotencyGuard();
        $request = Request::create('/api/orders/1', 'PUT');
        $request->headers->set('X-Idempotency-Key', 'put-key-123');

        $callCount = 0;
        $handler = function () use (&$callCount) {
            $callCount++;

            return new Response('updated', 200);
        };

        $middleware->handle($request, $handler);
        $response2 = $middleware->handle($request, $handler);

        $this->assertSame(1, $callCount);
        $this->assertSame('true', $response2->headers->get('X-Idempotent-Replayed'));
    }

    public function testShouldApply()
    {
        $middleware = new IdempotencyGuard();

        $this->assertTrue($this->callMethod($middleware, 'shouldApply', [Request::create('/', 'POST')]));
        $this->assertTrue($this->callMethod($middleware, 'shouldApply', [Request::create('/', 'PUT')]));
        $this->assertTrue($this->callMethod($middleware, 'shouldApply', [Request::create('/', 'PATCH')]));
        $this->assertFalse($this->callMethod($middleware, 'shouldApply', [Request::create('/', 'GET')]));
        $this->assertFalse($this->callMethod($middleware, 'shouldApply', [Request::create('/', 'DELETE')]));
    }
}
