<?php

namespace HughCube\Laravel\Knight\Http\Actions\Scheduling;

use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 统一调度触发网关 Action (/devops/schedule).
 *
 * 推荐路由配置：
 * Route::any('/schedule', ScheduleAction::class)->middleware([VerifyScheduleToken::class]);
 *
 * 推荐中间件组合：
 * - 专属中间件：[VerifyScheduleToken::class]
 *   1. 阿里云 FC 环境 (KNIGHT_IS_FC=true)：
 *      - 仅允许 FC 平台内部控制路径调用 (校验 x-fc-control-path，严禁 /http-invoke 公网触发器)；
 *      - 内部定时触发器免密放行。
 *   2. 常规环境 (ECS / Docker / K8s / 本地)：
 *      - 强制从 Query 或 Header 中比对 X-Devops-Token 是否与环境变量 KNIGHT_DEVOPS_TOKEN 一致。
 */
class ScheduleAction extends Controller
{
    /**
     * @return Response
     *
     * @throws Throwable
     */
    protected function action(): Response
    {
        $runner = new ScheduleRunner($this->getContainer());
        $report = $runner->run(true);

        return $this->asSuccess($report);
    }
}
