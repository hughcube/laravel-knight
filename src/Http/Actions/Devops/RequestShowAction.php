<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 完整请求报文回显诊断端点 (URI、Headers、Body、Server、Client IP).
 *
 * 推荐路由配置：
 * Route::any('/request', RequestShowAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产推荐：[VerifyDevopsToken::class] (严格防范网关入参与敏感反代头信息外泄)
 * - 结合内网：[VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 *
 * 支持参数：
 * - ?x-log=1 (或 Header x-log: 1)：触发将该次请求的完整 URI、Headers 与 Body 写入应用日志
 */
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
            'uri'       => $request->getUri(),
            'https'     => $request->isSecure(),
            'method'    => $request->getMethod(),
            'host'      => $request->getHost(),
            'client_ip' => $request->getClientIp(),
            'server'    => $request->server->all(),
            'env'       => $_ENV,
            'headers'   => $request->headers->all(),
            'content'   => serialize($request->getContent()),
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
