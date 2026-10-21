<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use Fruitcake\Cors\CorsService;
use HughCube\Laravel\Knight\Contracts\Support\GetUserLoginAccessSecret;
use HughCube\Laravel\Knight\Http\Middleware\HandleAllPathCors;
use HughCube\Laravel\Knight\Http\Middleware\LogRequest;
use HughCube\Laravel\Knight\Http\Middleware\RequestSignatureValidate;
use HughCube\Laravel\Knight\Http\Middleware\SetHstsHeader;
use HughCube\Laravel\Knight\Http\Middleware\SetHstsHeaderIfHttps;
use HughCube\Laravel\Knight\Http\Middleware\TrustHosts;
use HughCube\Laravel\Knight\Http\Middleware\TrustIps;
use HughCube\Laravel\Knight\Http\Middleware\TrustProxies;
use HughCube\Laravel\Knight\Http\Request as KnightRequest;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MiscMiddlewareTest extends TestCase
{
    private function makeSignedRequest(string $uri, string $method, array $headers, string $content): KnightRequest
    {
        $request = KnightRequest::create($uri, $method, [], [], [], [], $content);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    public function testHandleAllPathCorsMatchesAnyPath()
    {
        if (!class_exists(CorsService::class)) {
            $this->markTestSkipped('Fruitcake CorsService is not available.');
        }

        $middleware = new HandleAllPathCors($this->app, new CorsService());
        $request = Request::create('/cors', 'GET');

        $this->assertTrue($this->callMethod($middleware, 'hasMatchingPath', [$request]));
    }

    public function testLogRequestWritesLog()
    {
        $handler = $this->setupTestLogHandler();
        $initialCount = $handler !== null ? count($handler->getRecords()) : 0;

        $middleware = new LogRequest();
        $request = Request::create('/log', 'POST');
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());

        if ($handler !== null) {
            $this->assertGreaterThan($initialCount, count($handler->getRecords()), 'Expected log to be written');
        } else {
            $this->assertTrue(true, 'Log handler not available, skipping log assertion');
        }
    }

    public function testRequestSignatureValidatePassesWithValidSignature()
    {
        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest(
            '/signed',
            'POST',
            [
                'Date'         => 'Mon, 02 Jan 2006 15:04:05 GMT',
                'Content-Type' => 'application/json',
                'Nonce'        => '1234567890',
            ],
            '{"a":1}'
        );

        $request->setUserResolver(function () {
            return new class() implements GetUserLoginAccessSecret {
                public function getUserLoginAccessSecret(): ?string
                {
                    return 'secret';
                }
            };
        });

        $signature = $this->callMethod($middleware, 'makeSignature', [$request]);
        $request->headers->set('Signature', $signature);

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testRequestSignatureValidateThrowsOnInvalidSignature()
    {
        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest(
            '/signed',
            'POST',
            [
                'Date'         => 'Mon, 02 Jan 2006 15:04:05 GMT',
                'Content-Type' => 'application/json',
                'Nonce'        => '1234567890',
                'Signature'    => 'invalid',
            ],
            '{"a":1}'
        );

        $request->setUserResolver(function () {
            return new class() implements GetUserLoginAccessSecret {
                public function getUserLoginAccessSecret(): ?string
                {
                    return 'secret';
                }
            };
        });

        $this->expectException(\HughCube\Laravel\Knight\Exceptions\ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testRequestSignatureValidateSkipsOptionalPath()
    {
        config(['signature.optional' => ['optional']]);

        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest('/optional', 'GET', [], '');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testRequestSignatureValidateFailsOnMissingSignature()
    {
        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest('/signed', 'GET', [
            'Nonce' => '1234567890',
        ], '');

        $this->expectException(\HughCube\Laravel\Knight\Exceptions\ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testRequestSignatureValidateFailsOnShortNonce()
    {
        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest('/signed', 'GET', [
            'Nonce'     => 'short',
            'Signature' => 'any',
        ], '');

        $this->expectException(\HughCube\Laravel\Knight\Exceptions\ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testRequestSignatureValidateDisabledByConfig()
    {
        config(['signature.disabled' => true]);

        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest('/signed', 'GET', [], '');

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function testRequestSignatureValidateNotDisabledByDefault()
    {
        config(['signature.disabled' => false]);

        $middleware = new RequestSignatureValidate();
        $request = $this->makeSignedRequest('/signed', 'GET', [], '');

        $this->expectException(\HughCube\Laravel\Knight\Exceptions\ValidateSignatureException::class);
        $middleware->handle($request, function () {
            return new Response('ok');
        });
    }

    public function testParseRequestDatePrefersClientDate()
    {
        $middleware = new RequestSignatureValidate();

        $request = new class() extends KnightRequest {
            public function getClientHeaderPrefix(): string
            {
                return 'X-Client-';
            }
        };
        $request->headers->set('X-Client-Date', 'Mon, 02 Jan 2006 15:04:05 GMT');
        $request->headers->set('Date', 'Tue, 03 Jan 2006 15:04:05 GMT');

        $date = $this->callMethod($middleware, 'parseRequestDate', [$request]);

        $this->assertSame('Mon, 02 Jan 2006 15:04:05 GMT', $date);
    }

    public function testSetHstsHeaderAlwaysSetsHeader()
    {
        $middleware = new SetHstsHeader();
        $response = $middleware->handle(Request::create('/', 'GET'), function () {
            return new Response('ok');
        });

        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    public function testSetHstsHeaderIfHttpsOnlySetsWhenSecure()
    {
        $middleware = new SetHstsHeaderIfHttps();

        $secureRequest = Request::create('/', 'GET', [], [], [], ['HTTPS' => 'on']);
        $secureResponse = $middleware->handle($secureRequest, function () {
            return new Response('ok');
        });
        $this->assertSame('max-age=31536000', $secureResponse->headers->get('Strict-Transport-Security'));

        $plainResponse = $middleware->handle(Request::create('/', 'GET'), function () {
            return new Response('ok');
        });
        $this->assertNull($plainResponse->headers->get('Strict-Transport-Security'));
    }

    public function testTrustHostsUsesAppUrl()
    {
        config(['app.url' => 'https://example.test']);

        $middleware = new TrustHosts($this->app);
        $hosts = $middleware->hosts();

        $this->assertCount(1, $hosts);
        $this->assertStringContainsString('example\\.test', $hosts[0]);
    }

    public function testTrustIpsAllowsWhenEmptyTrustIps()
    {
        $middleware = new TrustIps($this->app);
        $response = $middleware->handle(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']), function () {
            return new Response('ok');
        }, '*');

        $this->assertSame('ok', $response->getContent());
    }

    public function testTrustIpsDeniesWhenIpNotTrusted()
    {
        $middleware = new TrustIps($this->app);

        $this->expectException(AuthorizationException::class);
        $middleware->handle(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']), function () {
            return new Response('ok');
        }, '127.0.0.1');
    }

    public function testTrustIpsAllowsWhenIpIsTrusted()
    {
        $middleware = new TrustIps($this->app);
        $response = $middleware->handle(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']), function () {
            return new Response('ok');
        }, '127.0.0.1,10.0.0.0/8');

        $this->assertSame('ok', $response->getContent());
    }

    public function testTrustIpsAllowsWhenRequestIpOrResolvedIpsIsEmpty()
    {
        $middleware = new TrustIps($this->app);

        $this->assertTrue($this->callMethod($middleware, 'isTrustIp', ['', '127.0.0.1']));
        $this->assertTrue($this->callMethod($middleware, 'isTrustIp', ['127.0.0.1', ',,,']));
    }

    public function testTrustProxiesDefaults()
    {
        $middleware = new TrustProxies($this->app);

        $this->assertSame('*', $this->getProperty($middleware, 'proxies'));
        $this->assertIsInt($this->getProperty($middleware, 'headers'));
    }
}
