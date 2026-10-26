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
        putenv('KNIGHT_DEVOPS_TOKEN');
        putenv('KNIGHT_IS_FC');

        parent::tearDown();
    }

    public function testTokenModeRejectsWhenNoTokenConfigured()
    {
        putenv('KNIGHT_DEVOPS_TOKEN');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/status', 'GET');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('DevOps token is not configured in environment');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testTokenModeRejectsInvalidToken()
    {
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/status', 'GET', ['X-Devops-Token' => 'wrong-token']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid DevOps token');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testTokenModeAllowsValidQueryToken()
    {
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/status', 'GET', ['X-Devops-Token' => 'secret-token']);

        $response = $middleware->handle($request, function ($req) {
            return new Response('token-passed');
        });

        $this->assertSame('token-passed', $response->getContent());
    }

    public function testTokenModeAllowsValidHeaderToken()
    {
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/status', 'GET');
        $request->headers->set('X-Devops-Token', 'secret-token');

        $response = $middleware->handle($request, function ($req) {
            return new Response('header-passed');
        });

        $this->assertSame('header-passed', $response->getContent());
    }

    public function testDevopsTokenVerifiesEvenInFcEnvironment()
    {
        // 非 Schedule 接口不管是否处于 FC 环境，都必须校验 Token
        putenv('KNIGHT_IS_FC=true');
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyDevopsToken();
        $request = Request::create('/devops/status', 'GET');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid DevOps token');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }
}
