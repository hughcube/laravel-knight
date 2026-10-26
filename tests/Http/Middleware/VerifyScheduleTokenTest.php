<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\VerifyScheduleToken;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyScheduleTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('KNIGHT_IS_FC');
        putenv('KNIGHT_DEVOPS_TOKEN');

        parent::tearDown();
    }

    public function testFcEnvironmentAllowsValidControlPath()
    {
        putenv('KNIGHT_IS_FC=true');
        putenv('KNIGHT_DEVOPS_TOKEN'); // 未配置 Token

        $this->assertTrue(VerifyScheduleToken::isAliyunFc());

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST');
        $request->headers->set('x-fc-control-path', '/control-channel');

        $response = $middleware->handle($request, function ($req) {
            return new Response('fc-control-passed');
        });

        $this->assertSame('fc-control-passed', $response->getContent());
    }

    public function testFcEnvironmentRejectsHttpInvoke()
    {
        putenv('KNIGHT_IS_FC=true');

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST');
        $request->headers->set('x-fc-control-path', '/http-invoke');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unauthorized FC invocation');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testFcEnvironmentRejectsMissingControlPath()
    {
        putenv('KNIGHT_IS_FC=true');

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unauthorized FC invocation');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testNonFcEnvironmentRequiresToken()
    {
        putenv('KNIGHT_IS_FC=false');
        putenv('KNIGHT_DEVOPS_TOKEN');

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('DevOps token is not configured in environment');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testNonFcEnvironmentRejectsInvalidToken()
    {
        putenv('KNIGHT_IS_FC=false');
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST', ['X-Devops-Token' => 'wrong']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid DevOps token');

        $middleware->handle($request, function ($req) {
            return new Response('should-not-pass');
        });
    }

    public function testNonFcEnvironmentAllowsValidToken()
    {
        putenv('KNIGHT_IS_FC=false');
        putenv('KNIGHT_DEVOPS_TOKEN=secret-token');

        $middleware = new VerifyScheduleToken();
        $request = Request::create('/devops/schedule', 'POST');
        $request->headers->set('X-Devops-Token', 'secret-token');

        $response = $middleware->handle($request, function ($req) {
            return new Response('schedule-passed');
        });

        $this->assertSame('schedule-passed', $response->getContent());
    }
}
