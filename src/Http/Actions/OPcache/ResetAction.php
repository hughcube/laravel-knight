<?php

namespace HughCube\Laravel\Knight\Http\Actions\OPcache;

use Exception;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 重置与刷新 PHP OPcache 字节码缓存.
 *
 * 推荐路由配置：
 * Route::any('/opcache/reset', ResetAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产极力推荐：[VerifyDevopsToken::class, OnlyLocalGuard::class] 或 [VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 * - 高危变更操作：执行 opcache_reset() 会瞬间清空所有编译好的脚本字节码并引发短期 CPU 抖动，严禁公网调用
 */
class ResetAction extends Controller
{
    use LoadedOPcacheExtension;

    /**
     * @throws Exception
     */
    protected function action(): Response
    {
        $startTime = microtime(true);
        $request = $this->getRequest();

        $this->loadedOPcacheExtension();

        if (! opcache_reset()) {
            throw new Exception('Failed to reset OPcache.');
        }

        $duration = microtime(true) - $startTime;

        Log::info(sprintf(
            'OPcache reset - IP: %s, User-Agent: %s, Duration: %sms, URL: %s',
            $request->ip(),
            $request->userAgent(),
            round($duration * 1000, 2),
            $request->fullUrl()
        ));

        return $this->asSuccess();
    }
}
