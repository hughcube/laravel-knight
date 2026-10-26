<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Exceptions\AuthUserInstanceExpectException;
use HughCube\Laravel\Knight\Exceptions\AuthUserNotAvailableException;
use HughCube\Laravel\Knight\Http\Middleware\CheckAuthUserInstanceOf;
use HughCube\Laravel\Knight\Http\Middleware\CheckAuthUserIsAvailable;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthUserMiddlewareTest extends TestCase
{
    public function testCheckAuthUserInstanceOfAllowsWhenNoUser()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $middleware = new CheckAuthUserInstanceOf();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, \stdClass::class);

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAuthUserInstanceOfAllowsExpected()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return new \stdClass();
        });

        $middleware = new CheckAuthUserInstanceOf();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, \stdClass::class);

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAuthUserInstanceOfThrowsWhenUnexpected()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return new \stdClass();
        });

        $middleware = new CheckAuthUserInstanceOf();

        $this->expectException(AuthUserInstanceExpectException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        }, \ArrayObject::class);
    }

    public function testCheckAuthUserIsAvailableAllowsWhenNoUser()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $middleware = new CheckAuthUserIsAvailable();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAuthUserIsAvailableAllowsWhenAvailable()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return new class()
            {
                public function isAvailable(): bool
                {
                    return true;
                }
            };
        });

        $middleware = new CheckAuthUserIsAvailable();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAuthUserIsAvailableThrowsWhenUnavailable()
    {
        $request = Request::create('/auth', 'GET');
        $request->setUserResolver(function () {
            return new class()
            {
                public function isAvailable(): bool
                {
                    return false;
                }
            };
        });

        $middleware = new CheckAuthUserIsAvailable();

        $this->expectException(AuthUserNotAvailableException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }
}
