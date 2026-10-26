<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/29
 * Time: 16:50.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use HughCube\Laravel\Knight\Exceptions\AuthUserInstanceExpectException;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthUserInstanceOf
{
    /**
     * @throws AuthUserInstanceExpectException
     */
    public function handle($request, $next, ...$expects): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        foreach ($expects as $expect) {
            if (class_exists($expect) && $request->user() instanceof $expect) {
                return $next($request);
            }
        }

        throw new AuthUserInstanceExpectException();
    }
}
