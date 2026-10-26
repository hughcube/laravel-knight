<?php

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    /**
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldProcess($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (false === $content) {
            return $response;
        }

        $etag = $this->generateETag($content);
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if (null !== $ifNoneMatch && $ifNoneMatch === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }

    /**
     * @param  Request  $request
     * @param  Response  $response
     * @return bool
     */
    protected function shouldProcess(Request $request, Response $response): bool
    {
        if (! in_array(strtoupper($request->method()), ['GET', 'HEAD'])) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return true;
    }

    /**
     * @param  string  $content
     * @return string
     */
    protected function generateETag(string $content): string
    {
        return '"'.md5($content).'"';
    }
}
