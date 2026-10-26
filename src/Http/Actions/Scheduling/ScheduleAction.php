<?php

namespace HughCube\Laravel\Knight\Http\Actions\Scheduling;

use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 统一调度触发网关 Action (/devops/schedule).
 *
 * 供 Docker Compose 伴生 pulse 容器或外部定时探针发起心跳调用。
 * 鉴权与幂等门禁交由中间件处理。
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
