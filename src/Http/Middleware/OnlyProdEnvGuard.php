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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OnlyProdEnvGuard
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment('prod')) {
            throw new AccessDeniedHttpException('Only accessible in prod env!');
        }

        return $next($request);
    }
}
