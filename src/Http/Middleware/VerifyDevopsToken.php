<?php

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyDevopsToken
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->validateToken($request);

        return $next($request);
    }

    /**
     * 获取服务端配置的 Token (直接从 OS 环境变量动态获取，脱离镜像与 config:cache)
     */
    public static function getExpectedToken(): ?string
    {
        $token = getenv('KNIGHT_DEVOPS_TOKEN');
        if (false !== $token && '' !== trim(strval($token))) {
            return trim(strval($token)) ?: null;
        }

        return null;
    }

    /**
     * 校验 X-Devops-Token (严格仅支持 Query 与 Header 两种传递方式)
     */
    protected function validateToken(Request $request): void
    {
        $expectedToken = static::getExpectedToken();

        // 运行期未注入 Token 时绝对拒绝，防止裸奔
        if (empty($expectedToken)) {
            throw new AccessDeniedHttpException('Forbidden: DevOps token is not configured in environment.');
        }

        $givenToken = $this->parseToken($request);

        if (empty($givenToken) || ! hash_equals(strval($expectedToken), strval($givenToken))) {
            throw new AccessDeniedHttpException('Forbidden: Invalid DevOps token.');
        }
    }

    /**
     * 解析请求携带的 Token (仅从 Query 与 Header 提取 X-Devops-Token)
     */
    protected function parseToken(Request $request): ?string
    {
        $queryToken = $request->query('X-Devops-Token');
        if (! empty($queryToken)) {
            return strval($queryToken);
        }

        $headerToken = $request->header('X-Devops-Token');
        if (! empty($headerToken)) {
            return strval($headerToken);
        }

        return null;
    }
}
