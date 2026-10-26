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

class OnlyLocalGuard
{
    protected function allowIps(): array
    {
        $ips = ['127.0.0.1'];

        if (app()->environment('local')) {
            $ips[] = '0.0.0.0/0';
        }

        return $ips;
    }

    public function handle(Request $request, Closure $next)
    {
        if (! IpUtils::checkIp($request->getClientIp(), $this->allowIps())) {
            throw new AccessDeniedHttpException('Only allow local access!');
        }

        return $next($request);
    }
}
