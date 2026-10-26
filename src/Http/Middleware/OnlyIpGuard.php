<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/8/31
 * Time: 18:21.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OnlyIpGuard
{
    public function handle(Request $request, Closure $next, ...$ips)
    {
        if (! IpUtils::checkIp($request->getClientIp(), $ips)) {
            throw new AccessDeniedHttpException('Only allow specified ip access!');
        }

        return $next($request);
    }
}
