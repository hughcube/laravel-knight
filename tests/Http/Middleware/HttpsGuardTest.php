<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/24
 * Time: 15:56.
 */

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\HttpsGuard;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class HttpsGuardTest extends TestCase
{
    public static function getCases(): array
    {
        $appUrls = [
            ['http://example.com', false],
            ['https://example.com', true],
        ];

        $hosts = [
            ['127.0.0.1', false],
            [null, false],
            ['example.com', true],
        ];

        $secureServers = [
            [['HTTPS' => 'off'], true],
            [['HTTPS' => null], true],
            [['HTTPS' => 'on'], false],
        ];

        $excepts = [
            [
                function () {
                    return true;
                },
                false,
            ],
            [
                function () {
                    return false;
                },
                true,
            ],
        ];

        $cases = [];
        foreach ($appUrls as $appUrl) {
            foreach ($hosts as $host) {
                foreach ($secureServers as $secureServer) {
                    foreach (['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT'] as $method) {
                        foreach ($excepts as $except) {
                            $bIs = $appUrl[1] && $host[1] && $secureServer[1] && $except[1];

                            /** 普通情况 */
                            $requestServer = [];
                            $requestServer = array_merge($requestServer, $secureServer[0]);
                            $requestServer = array_merge($requestServer, ['HTTP_HOST' => $host[0]]);
                            $request = static::createRequest($requestServer, Str::random(), $method);
                            $cases[] = [$appUrl[0], $request, $bIs ? 301 : 200, $except[0]];
                        }
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @dataProvider getCases
     */
    #[DataProvider('getCases')]
    public function testHandle(string $appUrl, Request $request, int $statusCode, $except = null)
    {
        if (! empty($except)) {
            HttpsGuard::customExcept(__METHOD__, $except);
        }

        $this->app['config']->set('app.url', $appUrl);
        $guard = $this->makeGuard();

        $response = $guard->handle($request, function (Request $request) {
            return new Response();
        });

        $this->assertSame($response->getStatusCode(), $statusCode);
    }

    protected function makeGuard(): HttpsGuard
    {
        return $this->app->make(HttpsGuard::class);
    }

    protected static function createRequest(array $server = [], $uri = 'test', $method = 'GET'): Request
    {
        $httpHost = Arr::get($server, 'HTTP_HOST', 'example.com');

        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            array_merge(['SERVER_NAME' => $httpHost, 'HTTP_HOST' => $httpHost], $server)
        );
    }
}
