<?php

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyDevopsToken
{
    /**
     * @var string|null 运行时覆盖的 token (测试/代码级注入)
     */
    protected static ?string $token = null;

    /**
     * @var string|null 运行时覆盖的 auth_mode (测试/代码级注入)
     */
    protected static ?string $authMode = null;

    public static function setToken(?string $token): void
    {
        static::$token = $token;
    }

    public static function setAuthMode(?string $mode): void
    {
        static::$authMode = $mode;
    }

    /**
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $this->getAuthMode();

        // 1. 禁用鉴权（开发环境放行）
        if ('disabled' === $mode) {
            return $next($request);
        }

        // 2. 阿里云 FC 模式（只信任 FC 平台内部控制路径）
        if ('fc' === $mode) {
            $this->validateAliFc($request);

            return $next($request);
        }

        // 3. Token 模式（比对 X-Devops-Token）
        $this->validateToken($request);

        return $next($request);
    }

    /**
     * 获取鉴权模式 (直接从 OS 环境变量动态获取，脱离镜像与 config:cache).
     */
    public static function getAuthMode(): string
    {
        if (null !== static::$authMode) {
            return strtolower(trim(static::$authMode));
        }

        $mode = getenv('KNIGHT_DEVOPS_AUTH_MODE');

        return false !== $mode && '' !== trim((string) $mode)
            ? strtolower(trim((string) $mode))
            : 'token';
    }

    /**
     * 获取服务端期望配置的 Token (直接从 OS 环境变量动态获取).
     */
    public static function getExpectedToken(): ?string
    {
        if (null !== static::$token) {
            return static::$token;
        }

        $token = getenv('KNIGHT_DEVOPS_TOKEN');

        return false !== $token && '' !== trim((string) $token)
            ? trim((string) $token)
            : null;
    }

    /**
     * 校验阿里云 FC 内部调用.
     */
    protected function validateAliFc(Request $request): void
    {
        $paths = Collection::wrap($request->header('x-fc-control-path'));

        // 必须含有控制路径且绝对不能为公网 HTTP 触发器 (/http-invoke)
        if ($paths->isNotEmpty() && ! $paths->containsStrict('/http-invoke')) {
            return;
        }

        throw new AccessDeniedHttpException('Forbidden: Unauthorized FC invocation.');
    }

    /**
     * 校验 X-Devops-Token.
     */
    protected function validateToken(Request $request): void
    {
        $expectedToken = static::getExpectedToken();

        // 运行期未注入 Token 时绝对拒绝，防止裸奔
        if (empty($expectedToken)) {
            throw new AccessDeniedHttpException('Forbidden: DevOps token is not set in environment.');
        }

        $givenToken = $this->parseToken($request);

        if (empty($givenToken) || ! hash_equals((string) $expectedToken, (string) $givenToken)) {
            throw new AccessDeniedHttpException('Forbidden: Invalid DevOps token.');
        }
    }

    /**
     * 解析请求携带的 Token (优先从 Query 提取，兼容 Header).
     */
    protected function parseToken(Request $request): ?string
    {
        $queryToken = $request->query('X-Devops-Token');
        if (! empty($queryToken)) {
            return (string) $queryToken;
        }

        $headerToken = $request->header('X-Devops-Token');
        if (! empty($headerToken)) {
            return (string) $headerToken;
        }

        return null;
    }
}
