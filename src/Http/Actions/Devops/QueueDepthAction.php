<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

/**
 * 异步队列积压深度探针 (用于 KEDA 自动弹性扩缩容、Prometheus 监控抓取或巡检大盘).
 *
 * 推荐路由配置：
 * Route::any('/queue-depth', QueueDepthAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产推荐：[VerifyDevopsToken::class] (防范队列拓扑与业务堆积指标裸露)
 * - 结合内网：[VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 *
 * 支持参数：
 * - ?x-connection=redis (或 Query connection)：指定队列连接名，缺省使用 queue.default
 * - ?x-queues=default,high (或 Query queues)：指定逗号分隔的队列名清单，缺省为 'default'
 */
class QueueDepthAction extends Controller
{
    /**
     * @return array<string, array<string>>
     */
    protected function rules(): array
    {
        return [
            'x-connection' => ['nullable', 'string'],
            'x-queues'     => ['nullable', 'string'],
        ];
    }

    /**
     * @return Response
     */
    protected function action(): Response
    {
        $connection = $this->p('x-connection') ?: $this->getRequest()->query('connection') ?: config('queue.default');

        $queuesParam = $this->p('x-queues') ?: $this->getRequest()->query('queues') ?: 'default';

        $queues = array_filter(array_map('trim', explode(',', strval($queuesParam))));

        $sizes = [];
        foreach ($queues as $queue) {
            $sizes[$queue] = Queue::connection($connection)->size($queue);
        }

        $response = $this->asSuccess(['value'  => array_sum($sizes), 'queues' => $sizes,]);

        Log::info('QueueDepth', [
            'ip'         => $this->getRequest()->getClientIp(),
            'ua'         => $this->getRequest()->userAgent(),
            'connection' => $connection,
            'queues'     => $queuesParam,
            'status'     => $response->getStatusCode(),
        ]);

        return $response;
    }
}
