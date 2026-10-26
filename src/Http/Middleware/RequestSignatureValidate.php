<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/7/27
 * Time: 21:44.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use HughCube\Laravel\Knight\Contracts\Support\GetUserLoginAccessSecret;
use HughCube\Laravel\Knight\Exceptions\ValidateSignatureException;
use HughCube\Laravel\Knight\Ide\Http\KIdeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class RequestSignatureValidate
{
    protected $optional = null;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     *
     * @throws ValidateSignatureException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isDisabled() || $this->validate($request) || $this->isOptional($request)) {
            return $next($request);
        }

        throw new ValidateSignatureException();
    }

    protected function isDisabled(): bool
    {
        return true === config('signature.disabled');
    }

    /**
     * 时区为GMT的RFC1123格式，例如Mon, 02 Jan 2006 15:04:05 GMT.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc1123
     */
    protected function parseRequestDate($request)
    {
        /** @var Request|KIdeRequest $request */
        return $request->getClientDate() ?: $request->getDate() ?: null;
    }

    /**
     * @param  Request|KIdeRequest  $request
     * @return bool
     *
     * @phpstan-ignore-next-line
     */
    protected function validate(Request $request): bool
    {
        /** @phpstan-ignore-next-line */
        $signature = $request->getClientSignature();
        if (null == $signature) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        $nonce = $request->getClientNonce();
        if (empty($nonce) || 10 > strlen($nonce)) {
            return false;
        }

        return $this->makeSignature($request) === $signature;
    }

    /**
     * @param  Request|KIdeRequest  $request
     *
     * @phpstan-ignore-next-line
     */
    protected function makeSignature(Request $request): ?string
    {
        /** @var null|GetUserLoginAccessSecret $user */
        $user = $request->user();

        $string = sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n%s",
            /** HTTP METHOD */
            strtoupper($request->getMethod()),
            /** HTTP URL */
            $request->getRequestUri(),
            /** HTTP DATE */
            $this->parseRequestDate($request) ?: '',
            /** HTTP CONTENT TYPE */
            $request->headers->get('CONTENT_TYPE', ''),
            /** HTTP CLIENT HEADERS */
            Collection::make($request->getClientHeaders()->all())
                ->forget([
                    /** 签名字符串 */
                    strtolower(sprintf('%sSignature', $request->getClientHeaderPrefix())),
                ])
                ->sortKeys()
                ->map(function ($value, $key) {
                    return sprintf('%s=%s', $key, implode(',', Arr::wrap($value)));
                })
                ->implode('&'),
            /** HTTP CONTENT */
            $request->getContent(),
            /** USER ACCESS SECRET */
            $user instanceof GetUserLoginAccessSecret ? $user->getUserLoginAccessSecret() : ''
        );

        return md5($string);
    }

    protected function isOptional(Request $request): bool
    {
        $optional = Collection::make($this->getOptional())
            ->map(function ($optional) {
                if (is_string($optional)) {
                    return ltrim($optional, '/');
                }

                return $optional;
            })
            ->values()->all();

        return $request->is($optional) || $request->fullUrlIs($optional);
    }

    protected function getOptional()
    {
        return ($this->optional ?: config('signature.optional')) ?: [];
    }
}
