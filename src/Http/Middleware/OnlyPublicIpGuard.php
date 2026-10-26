<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/8/31
 * Time: 18:21.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use HughCube\Laravel\Knight\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OnlyPublicIpGuard
{
    public function handle(Request $request, Closure $next)
    {
        if (! Str::isPublicIp($request->getClientIp())) {
            throw new AccessDeniedHttpException('Only allow specified intranet ip access!');
        }

        return $next($request);
    }
}
