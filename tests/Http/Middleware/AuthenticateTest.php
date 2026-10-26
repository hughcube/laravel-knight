<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\Authenticate;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTest extends TestCase
{
    private function makeMiddleware(array $optional): Authenticate
    {
        return new class($optional) extends Authenticate
        {
            public $optional = [];

            public function __construct(array $optional)
            {
                $this->optional = $optional;
            }

            protected function authenticate($request, array $guards)
            {
                throw new AuthenticationException('Unauthenticated.');
            }
        };
    }

    public function testOptionalPathSkipsAuthenticationException()
    {
        $middleware = $this->makeMiddleware(['optional']);
        $request = Request::create('/optional', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testNonOptionalPathThrowsAuthenticationException()
    {
        $middleware = $this->makeMiddleware(['optional']);
        $request = Request::create('/secure', 'GET');

        $this->expectException(AuthenticationException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testOptionalFullUrlSkipsAuthenticationException()
    {
        config(['authenticate.optional' => ['https://example.test/optional']]);

        $middleware = $this->makeMiddleware([]);
        $request = Request::create('https://example.test/optional', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testAuthenticatedRequestPassesThrough()
    {
        $middleware = new class([]) extends Authenticate
        {
            public $optional = [];

            public function __construct(array $optional)
            {
                $this->optional = $optional;
            }

            protected function authenticate($request, array $guards)
            {
            }
        };

        $request = Request::create('/secure', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }
}
