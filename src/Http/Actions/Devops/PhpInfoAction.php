<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Hughcube\PhpInfo\PhpInfo;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHP 全量运行期配置与模块信息页面 (phpinfo).
 *
 * 推荐路由配置：
 * Route::any('/phpinfo', PhpInfoAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产极力推荐：[VerifyDevopsToken::class, OnlyLocalGuard::class] 或 [VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 * - 极度高危：包含宿主机全量环境变量、编译参数与扩展配置，严禁公网裸露
 */
class PhpInfoAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        return new Response(PhpInfo::html(), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
