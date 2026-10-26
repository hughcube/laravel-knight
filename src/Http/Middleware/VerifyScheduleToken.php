<?php

namespace HughCube\Laravel\Knight\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * ScheduleAction 专属鉴权中间件.
 *
 * 推荐路由配置：
 * Route::any('/schedule', ScheduleAction::class)->middleware([VerifyScheduleToken::class]);
 *
 * 鉴权策略：
 * 1. 阿里云函数计算 (FC) 环境 (KNIGHT_IS_FC=true)：
 *    - 仅允许 FC 平台内部控制路径触发（请求必须携带 x-fc-control-path，且绝对不能包含 /http-invoke 公网触发器）；
 *    - 满足内部路径时免密放行，否则 403 拒绝。
 * 2. 非 FC 环境 (ECS / Docker / K8s / 本地)：
 *    - 强制从 Query 或 Header 中校验 X-Devops-Token 是否与环境变量 KNIGHT_DEVOPS_TOKEN 匹配。
 */
class VerifyScheduleToken extends VerifyDevopsToken
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
        // 1. 处于阿里云函数计算 (FC) 容器内：校验内部控制路径，通过后免密放行
        if (static::isAliyunFc()) {
            $this->validateAliyunFcControlPath($request);

            return $next($request);
        }

        // 2. 非 FC 环境：强制执行 X-Devops-Token 验证
        return parent::handle($request, $next);
    }

    /**
     * 校验阿里云 FC 内部控制路径 (严禁公网 /http-invoke 触发调度)
     */
    protected function validateAliyunFcControlPath(Request $request): void
    {
        $paths = Collection::wrap($request->header('x-fc-control-path'));

        // 必须含有控制路径且绝对不能为公网 HTTP 触发器 (/http-invoke)
        if ($paths->isNotEmpty() && ! $paths->containsStrict('/http-invoke')) {
            return;
        }

        throw new AccessDeniedHttpException('Forbidden: Unauthorized FC invocation.');
    }

    /**
     * 判定当前运行环境是否为阿里云函数计算 (FC) (仅读取单一环境变量 KNIGHT_IS_FC)
     */
    public static function isAliyunFc(): bool
    {
        $env = getenv('KNIGHT_IS_FC');

        return false !== $env && in_array(strtolower(trim(strval($env))), ['1', 'true'], true);
    }
}
