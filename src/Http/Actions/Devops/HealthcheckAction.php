<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Routing\Traits\DisableResponseCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * 极轻量存活与健康检查探针 (Liveness Probe).
 *
 * 推荐路由配置：
 * Route::any('/healthcheck', HealthcheckAction::class);
 *
 * 推荐中间件组合：
 * - 默认建议：无中间件 (零外部依赖，极速响应，专供 SLB/K8s/容器运行时探活探针调用)
 *
 * 响应说明：
 * - 成功时返回 200 HTTP 状态码与纯文本字符串 'success'
 */
class HealthcheckAction extends Controller
{
    use DisableResponseCache;

    /**
     * @return Response
     */
    protected function action(): Response
    {
        return $this->disableResponseCache(
            new Response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8'])
        );
    }
}
