<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/4/26
 * Time: 10:47.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class TrustIps
{
    /**
     * The application instance.
     *
     * @var Container
     */
    protected $app;

    /**
     * @var array
     */
    protected $trustIps = [];

    /**
     * Create a new middleware instance.
     *
     * @param  Container  $app
     * @return void
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @param  Request  $request
     * @param  callable  $next
     * @param  null|string|array  $trustIps
     * @return Response
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, callable $next, $trustIps = null): Response
    {
        if (! $this->isTrustIp($request->ip(), $trustIps ?? $this->trustIps)) {
            throw new AuthorizationException('An untrusted IP address!');
        }

        return $next($request);
    }

    protected function isTrustIp(string $requestIp, ?string $ips = null): bool
    {
        if (empty($ips) || '*' === $ips || '**' === $ips) {
            return true;
        }

        if (empty($requestIp)) {
            return true;
        }

        $ips = array_values(array_unique(array_filter(Arr::wrap(explode(',', $ips)))));
        if (empty($ips)) {
            return true;
        }

        if (IpUtils::checkIp($requestIp, $ips)) {
            return true;
        }

        return false;
    }
}
