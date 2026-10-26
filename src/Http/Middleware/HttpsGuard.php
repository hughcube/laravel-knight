<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/24
 * Time: 15:56.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use HughCube\PUrl\Url as PUrl;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpsGuard
{
    /**
     * @var callable[]
     */
    protected static $excepts = [];

    /**
     * The application instance.
     *
     * @var Container
     */
    protected $app;

    /**
     * @var callable[]
     */
    protected $disableCallable = [];

    /**
     * Create a new middleware instance.
     *
     * @param  Container  $app
     * @return void
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @param  Request  $request
     * @param  callable  $next
     * @param  int  $status
     * @param  string|null  $hsts
     * @return Response
     */
    public function handle(
        Request $request,
        callable $next,
        int $status = 301,
        ?string $hsts = 'max-age=31536000'
    ): Response {
        if (! $request->isSecure()
            && $this->isHostRequest($request)
            && $this->isSecureApplicationUrl()
            && ! $this->isExcept($request)
        ) {
            $url = PUrl::instance($request->getUri())->withScheme('https');

            return redirect()->to($url->toString(), $status);
        }

        /** @var Response $response */
        $response = $next($request);

        /**
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security
         */
        if (! empty($hsts) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }

    /**
     * @param  Request  $request
     * @return bool
     */
    protected function isExcept(Request $request): bool
    {
        foreach (static::$excepts as $except) {
            if ($except($request)) {
                return true;
            }
        }

        return false;
    }

    protected function isSecureApplicationUrl(): bool
    {
        $url = $this->app['config']->get('app.url');
        if (empty($url)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($scheme)) {
            return false;
        }

        return 'https' === strtolower($scheme);
    }

    protected function isHostRequest(Request $request): bool
    {
        $host = $request->getHost();

        return ! empty($host) && false === filter_var($host, FILTER_VALIDATE_IP);
    }

    public static function customExcept($name, callable $except)
    {
        static::$excepts[$name] = $except;
    }

    public static function resetCustomExcepts()
    {
        static::$excepts = [];
    }
}
