<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/29
 * Time: 16:32.
 */

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleAllPathCors
{
    /**
     * 保持对旧版构造函数的参数兼容
     */
    public function __construct(...$args)
    {
    }

    /**
     * 保持对旧版 hasMatchingPath 的兼容
     */
    protected function hasMatchingPath(Request $request): bool
    {
        return true;
    }
    /**
     * 是否已在 Worker 启动引导期完成预热
     */
    protected static bool $initialized = false;

    /**
     * 常驻内存预检单例 Response（通配符 * 或固定单域场景下完全零内存分配、零 GC）
     */
    protected static ?Response $immutablePreflightResponse = null;

    /**
     * 预烘焙好的静态 Header 字典（大小写与格式提前固化）
     */
    protected static array $bakedStaticHeaders = [];

    /**
     * 是否允许任意 Origin
     */
    protected static bool $allowAllOrigins = false;

    /**
     * 允许的具体 Origins 查找哈希表（以 key 为索引，O(1) 速度查找）
     * @var array<string, true>
     */
    protected static array $allowedOriginsMap = [];

    /**
     * 预编译好的 Origin 正则表达式列表
     * @var string[]
     */
    protected static array $compiledPatterns = [];

    /**
     * 是否支持凭证（Credentials）
     */
    protected static bool $supportsCredentials = false;

    /**
     * 动态多域名的预检 Response 单例缓存池（限制容量防内存泄漏）
     * @var array<string, Response>
     */
    protected static array $dynamicPreflightCache = [];

    /**
     * 跳过回调列表
     * @var array<int, \Closure(\Illuminate\Http\Request): bool>
     */
    protected static array $skipCallbacks = [];

    /**
     * 在 Octane Worker 启动时完成全量预编译
     */
    public static function warmup(?array $config = null): void
    {
        $config = $config ?? (function_exists('config') ? config('cors', []) : []);

        $origins = (array) ($config['allowed_origins'] ?? ['*']);
        static::$allowAllOrigins = in_array('*', $origins, true);
        static::$supportsCredentials = (bool) ($config['supports_credentials'] ?? false);

        // 1. O(1) 哈希表
        static::$allowedOriginsMap = array_fill_keys($origins, true);

        // 2. 预编译通配符模式为正则
        static::$compiledPatterns = [];
        foreach ((array) ($config['allowed_origins_patterns'] ?? []) as $pattern) {
            static::$compiledPatterns[] = $pattern;
        }

        // 3. 预烘焙不可变标准头部
        $methods = implode(', ', array_map('strtoupper', (array) ($config['allowed_methods'] ?? ['*'])));
        $headers = implode(', ', (array) ($config['allowed_headers'] ?? ['*']));
        $exposed = implode(', ', (array) ($config['exposed_headers'] ?? []));
        $maxAge = (string) ($config['max_age'] ?? 0);

        static::$bakedStaticHeaders = array_filter([
            'Access-Control-Allow-Methods' => $methods ?: '*',
            'Access-Control-Allow-Headers' => $headers ?: '*',
            'Access-Control-Expose-Headers' => $exposed ?: null,
            'Access-Control-Max-Age' => $maxAge !== '0' ? $maxAge : null,
        ]);

        if (static::$supportsCredentials) {
            static::$bakedStaticHeaders['Access-Control-Allow-Credentials'] = 'true';
        }

        // 4. 构建通配预检响应的常驻不可变单例
        if (static::$allowAllOrigins && !static::$supportsCredentials) {
            $preflightHeaders = array_merge(static::$bakedStaticHeaders, [
                'Access-Control-Allow-Origin' => '*',
            ]);
            static::$immutablePreflightResponse = new Response('', 204, $preflightHeaders);
        }

        static::$initialized = true;
    }

    public function handle(Request $request, Closure $next)
    {
        // 0. 跳过回调
        foreach (static::$skipCallbacks as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }

        // 1. 【极致短路】：非跨域请求（无 Origin 头）首行 0 纳秒放行
        $origin = $request->headers->get('Origin');
        if (null === $origin) {
            return $next($request);
        }

        if (!static::$initialized) {
            static::warmup();
        }

        // 2. O(1) 判定 Origin 是否合法
        $allowedOrigin = static::resolveAllowedOrigin($origin);
        if (null === $allowedOrigin) {
            return $next($request);
        }

        // 3. 【OPTIONS 预检极速短路】：直接返回常驻单例响应（零对象创建、零 GC）
        if ($request->isMethod('OPTIONS')) {
            return static::getPreflightResponse($allowedOrigin);
        }

        // 4. 执行后续管道与控制器
        $response = $next($request);

        // 5. 极速写入响应头
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        if (static::$supportsCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        if (!empty(static::$bakedStaticHeaders['Access-Control-Expose-Headers'])) {
            $response->headers->set('Access-Control-Expose-Headers', static::$bakedStaticHeaders['Access-Control-Expose-Headers']);
        }
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }

    protected static function resolveAllowedOrigin(string $origin): ?string
    {
        if (static::$allowAllOrigins && !static::$supportsCredentials) {
            return '*';
        }

        if (isset(static::$allowedOriginsMap[$origin])) {
            return $origin;
        }

        foreach (static::$compiledPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $origin;
            }
        }

        return null;
    }

    protected static function getPreflightResponse(string $allowedOrigin): Response
    {
        if ('*' === $allowedOrigin && null !== static::$immutablePreflightResponse) {
            return static::$immutablePreflightResponse;
        }

        if (isset(static::$dynamicPreflightCache[$allowedOrigin])) {
            return static::$dynamicPreflightCache[$allowedOrigin];
        }

        $headers = array_merge(static::$bakedStaticHeaders, [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
        ]);

        $response = new Response('', 204, $headers);

        if (count(static::$dynamicPreflightCache) < 100) {
            static::$dynamicPreflightCache[$allowedOrigin] = $response;
        }

        return $response;
    }

    public static function skipWhen(Closure $callback): void
    {
        static::$skipCallbacks[] = $callback;
    }

    public static function flushState(): void
    {
        static::$initialized = false;
        static::$immutablePreflightResponse = null;
        static::$bakedStaticHeaders = [];
        static::$allowedOriginsMap = [];
        static::$compiledPatterns = [];
        static::$dynamicPreflightCache = [];
        static::$skipCallbacks = [];
    }
}
