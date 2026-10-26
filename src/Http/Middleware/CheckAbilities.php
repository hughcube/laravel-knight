<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/29
 * Time: 16:50.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;

class CheckAbilities extends \Laravel\Sanctum\Http\Middleware\CheckAbilities
{
    public function handle($request, $next, ...$abilities): Response
    {
        if (! $request->user() || ! $request->user()->currentAccessToken()) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$abilities);
    }
}
