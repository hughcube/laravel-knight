<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/24
 * Time: 15:56.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetHstsHeader
{
    /**
     * @param  Request  $request
     * @param  callable  $next
     * @param  string|null  $hsts
     * @return Response
     */
    public function handle(Request $request, callable $next, ?string $hsts = 'max-age=31536000'): Response
    {
        /** @var Response $response */
        $response = $next($request);

        /**
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security
         */
        $response->headers->set('Strict-Transport-Security', $hsts);

        return $response;
    }
}
