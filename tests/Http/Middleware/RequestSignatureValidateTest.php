<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Exceptions\ValidateSignatureException;
use HughCube\Laravel\Knight\Http\Middleware\RequestSignatureValidate;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestSignatureValidateTest extends TestCase
{
    /**
     * 创建一个禁用签名验证的中间件实例（模拟 disabled 场景）.
     */
    private function makeDisabledMiddleware(): RequestSignatureValidate
    {
        return new class() extends RequestSignatureValidate {
            protected function isDisabled(): bool
            {
                return true;
            }
        };
    }

    /**
     * 创建一个签名验证始终失败的中间件实例，可自定义 optional 列表.
     */
    private function makeMiddleware(array $optional = []): RequestSignatureValidate
    {
        return new class($optional) extends RequestSignatureValidate {
            public $optional;

            public function __construct(array $optional)
            {
                $this->optional = $optional;
            }

            protected function isDisabled(): bool
            {
                return false;
            }

            protected function validate(Request $request): bool
            {
                return false;
            }
        };
    }

    public function testDisabledSkipsValidation()
    {
        $middleware = $this->makeDisabledMiddleware();
        $request = Request::create('/any-path', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testNonOptionalPathThrowsException()
    {
        $middleware = $this->makeMiddleware([]);
        $request = Request::create('/secure', 'GET');

        $this->expectException(ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testOptionalPathSkipsValidation()
    {
        $middleware = $this->makeMiddleware(['optional']);
        $request = Request::create('/optional', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testOptionalPathWithLeadingSlashSkipsValidation()
    {
        $middleware = $this->makeMiddleware(['/optional']);
        $request = Request::create('/optional', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testOptionalWildcardSkipsValidation()
    {
        $middleware = $this->makeMiddleware(['/api/public/*']);
        $request = Request::create('/api/public/health', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testOptionalFullUrlSkipsValidation()
    {
        config(['signature.optional' => ['https://example.test/optional']]);

        $middleware = $this->makeMiddleware([]);
        $request = Request::create('https://example.test/optional', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testNonMatchingOptionalThrowsException()
    {
        $middleware = $this->makeMiddleware(['optional']);
        $request = Request::create('/secure', 'GET');

        $this->expectException(ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }
}
