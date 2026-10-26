<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\VerifyDevopsToken;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyDevopsTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        VerifyDevopsToken::setAuthMode(null);
        VerifyDevopsToken::setToken(null);
        putenv('KNIGHT_DEVOPS_AUTH_MODE');
        putenv('KNIGHT_DEVOPS_TOKEN');

        parent::tearDown();
    }

    public function testDisabledModeAllowsAnyRequest()
    {
        VerifyDevopsToken::setAuthMode('disabled');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('passed');
        });

        $this->assertSame('passed', $response->getContent());
    }

    public function testFcModeWithValidControlPath()
    {
        VerifyDevopsToken::setAuthMode('fc');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');
        $request->headers->set('x-fc-control-path', '/control-channel');

        $response = $middleware->handle($request, function ($req) {
            return new Response('fc-passed');
        });

        $this->assertSame('fc-passed', $response->getContent());
    }

    public function testFcModeRejectsHttpInvoke()
    {
        VerifyDevopsToken::setAuthMode('fc');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');
        $request->headers->set('x-fc-control-path', '/http-invoke');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unauthorized FC invocation');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testFcModeRejectsEmptyControlPath()
    {
        VerifyDevopsToken::setAuthMode('fc');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unauthorized FC invocation');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testTokenModeRejectsWhenNoTokenConfigured()
    {
        VerifyDevopsToken::setAuthMode('token');
        VerifyDevopsToken::setToken(null);

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('DevOps token is not set');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testTokenModeRejectsInvalidToken()
    {
        VerifyDevopsToken::setAuthMode('token');
        VerifyDevopsToken::setToken('secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET', ['X-Devops-Token' => 'wrong-token']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid DevOps token');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testTokenModeAllowsValidQueryToken()
    {
        VerifyDevopsToken::setAuthMode('token');
        VerifyDevopsToken::setToken('secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET', ['X-Devops-Token' => 'secret-token']);

        $response = $middleware->handle($request, function ($req) {
            return new Response('token-passed');
        });

        $this->assertSame('token-passed', $response->getContent());
    }

    public function testTokenModeAllowsValidHeaderToken()
    {
        VerifyDevopsToken::setAuthMode('token');
        VerifyDevopsToken::setToken('secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/ping', 'GET');
        $request->headers->set('X-Devops-Token', 'secret-token');

        $response = $middleware->handle($request, function ($req) {
            return new Response('header-passed');
        });

        $this->assertSame('header-passed', $response->getContent());
    }

    public function testDynamicGetenvFallback()
    {
        putenv('KNIGHT_DEVOPS_AUTH_MODE=disabled');

        $this->assertSame('disabled', VerifyDevopsToken::getAuthMode());

        putenv('KNIGHT_DEVOPS_TOKEN=env-token');
        $this->assertSame('env-token', VerifyDevopsToken::getExpectedToken());
    }
}
