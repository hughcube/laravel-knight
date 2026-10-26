<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

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
        $connection = $this->p('x-connection')
            ?: $this->getRequest()->query('connection')
            ?: config('queue.default');

        $queuesParam = $this->p('x-queues')
            ?: $this->getRequest()->query('queues')
            ?: 'default';

        $queues = array_filter(array_map('trim', explode(',', strval($queuesParam))));

        $sizes = [];
        foreach ($queues as $queue) {
            $sizes[$queue] = Queue::connection($connection)->size($queue);
        }

        $response = $this->asSuccess([
            'value'  => array_sum($sizes),
            'queues' => $sizes,
        ]);

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
