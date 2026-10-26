<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestShowAction extends Controller
{
    /**
     * @return array<string, array<string>>
     */
    protected function rules(): array
    {
        return [
            'x-log' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return Response
     */
    protected function action(): Response
    {
        $request = $this->getRequest();

        $data = [
            'uri' => $request->getUri(),
            'https' => $request->isSecure(),
            'method' => $request->getMethod(),
            'host' => $request->getHost(),
            'client_ip' => $request->getClientIp(),
            'server' => $request->server->all(),
            'env' => $_ENV,
            'headers' => $request->headers->all(),
            'content' => serialize($request->getContent()),
        ];

        if (1 == $this->p('x-log') || '1' === $request->header('x-log')) {
            Log::info(sprintf(
                'request: uri:%s, headers:%s, body:%s',
                $data['uri'],
                json_encode($data['headers']),
                $data['content']
            ));
        }

        return $this->asSuccess($data);
    }
}
