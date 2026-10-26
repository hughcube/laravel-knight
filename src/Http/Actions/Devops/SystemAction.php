<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * 宿主系统与 PHP 运行期指标诊断 (OS、PHP版本、内存占用、已加载扩展).
 *
 * 推荐路由配置：
 * Route::any('/system', SystemAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产推荐：[VerifyDevopsToken::class] (严格防范系统版本与内存指标外泄)
 * - 结合内网：[VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 */
class SystemAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        return $this->asSuccess([
            'os' => php_uname(),
            'php_version' => PHP_VERSION,
            'current_memory_usage' => $this->asFileSize(memory_get_usage()),
            'peak_memory_usage' => $this->asFileSize(memory_get_peak_usage()),
            'php_extensions' => get_loaded_extensions(),
        ]);
    }

    protected function asFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
