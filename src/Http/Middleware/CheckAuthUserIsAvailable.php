<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/29
 * Time: 16:50.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use HughCube\Laravel\Knight\Exceptions\AuthUserNotAvailableException;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthUserIsAvailable
{
    /**
     * @throws AuthUserNotAvailableException
     */
    public function handle($request, $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if (is_object($user = $request->user()) && method_exists($user, 'isAvailable') && $user->isAvailable()) {
            return $next($request);
        }

        throw new AuthUserNotAvailableException();
    }
}
