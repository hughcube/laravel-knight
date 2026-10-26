<?php

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyGuard
{
    /**
     * @param  Request  $request
     * @param  Closure  $next
     * @param  int  $ttl
     * @return Response
     */
    public function handle(Request $request, Closure $next, $ttl = 86400)
    {
        $ttl = intval($ttl);

        if (! $this->shouldApply($request)) {
            return $next($request);
        }

        $idempotencyKey = $this->getIdempotencyKey($request);
        if (null === $idempotencyKey) {
            return $next($request);
        }

        $cacheKey = $this->getCacheKey($idempotencyKey);
        $cache = $this->getCacheStore();

        $cached = $cache->get($cacheKey);
        if (null !== $cached) {
            $response = $this->unserializeResponse($cached);
            $response->headers->set('X-Idempotent-Replayed', 'true');

            return $response;
        }

        $response = $next($request);

        $cache->put($cacheKey, $this->serializeResponse($response), $ttl);

        return $response;
    }

    /**
     * @param  Request  $request
     * @return string|null
     */
    protected function getIdempotencyKey(Request $request): ?string
    {
        $key = $request->header('X-Idempotency-Key');

        return is_string($key) && strlen($key) > 0 ? $key : null;
    }

    /**
     * @param  string  $idempotencyKey
     * @return string
     */
    protected function getCacheKey(string $idempotencyKey): string
    {
        return 'knight_idempotency:'.md5($idempotencyKey);
    }

    /**
     * @return Repository
     */
    protected function getCacheStore()
    {
        return Cache::store();
    }

    /**
     * @param  Request  $request
     * @return bool
     */
    protected function shouldApply(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH']);
    }

    /**
     * @param  Response  $response
     * @return string
     */
    protected function serializeResponse(Response $response): string
    {
        return serialize([
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => $response->getContent(),
        ]);
    }

    /**
     * @param  string  $data
     * @return Response
     */
    protected function unserializeResponse(string $data): Response
    {
        $decoded = unserialize($data);

        $response = new Response(
            $decoded['content'],
            $decoded['status'],
            $decoded['headers']
        );

        return $response;
    }
}
