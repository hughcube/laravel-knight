<?php

namespace HughCube\Laravel\Knight\Http\Actions\OPcache;

use Exception;
use HughCube\Laravel\Knight\Exceptions\UserException;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Routing\Traits\DisableResponseCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * OPcache 运行时内存与缓存脚本状态监控.
 *
 * 推荐路由配置：
 * Route::any('/opcache/states', StatesAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 生产推荐：[VerifyDevopsToken::class] (防范内存拓扑与文件绝对路径泄漏)
 * - 结合内网：[VerifyDevopsToken::class, OnlyPrivateIpGuard::class]
 *
 * 支持参数：
 * - ?x-format=json (或 ?x-as-json=1 或 Header Accept: application/json)：输出结构化 JSON 数据
 * - ?x-scripts=1 (或 Header x-scripts: 1)：在 JSON 模式下是否附带全量已缓存脚本文件路径数组
 * - 默认输出官方 UI 监控页面
 */
class StatesAction extends Controller
{
    use LoadedOPcacheExtension;
    use DisableResponseCache;

    /**
     * @return array<string, array<string>>
     */
    protected function rules(): array
    {
        return [
            'x-format'  => ['nullable', 'string'],
            'x-as-json' => ['nullable', 'boolean'],
            'x-scripts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return Response
     *
     * @throws Exception
     */
    protected function action(): Response
    {
        if (! extension_loaded('Zend OPcache')) {
            throw new UserException(
                'You do not have the Zend OPcache extension loaded, sample data is being shown instead.'
            );
        }

        $status = opcache_get_status();
        if (false === $status) {
            throw new UserException(
                'Failed to obtain the status of Zend OPcache. Please check whether it is disabled.'
            );
        }

        if ($this->isJson()) {
            $scripts = isset($status['scripts']) && is_array($status['scripts']) ? $status['scripts'] : [];
            $withScripts = 1 == $this->p('x-scripts') || '1' === $this->getRequest()->header('x-scripts');

            $data = [
                'opcache_enabled'     => $status['opcache_enabled'] ?? true,
                'cache_full'          => $status['cache_full'] ?? false,
                'restart_pending'     => $status['restart_pending'] ?? false,
                'restart_in_progress' => $status['restart_in_progress'] ?? false,
                'memory_usage'        => $status['memory_usage'] ?? [],
                'interned_strings'    => $status['interned_strings_usage'] ?? [],
                'statistics'          => $status['opcache_statistics'] ?? [],
                'scripts_count'       => count($scripts),
                'scripts'             => $withScripts ? array_keys($scripts) : null,
            ];

            return $this->asSuccess($data);
        }

        return $this->disableResponseCache(
            new Response($this->renderView(dirname(__DIR__, 3).'/OPcache/Views/opcache.php'))
        );
    }

    protected function renderView(string $file): string
    {
        $obLevel = ob_get_level();
        ob_start();
        include $file;

        return strval(ob_get_clean());
    }

    protected function isJson(): bool
    {
        return 'json' === strtolower(strval($this->p('x-format')))
            || 1 == $this->p('x-as-json')
            || $this->getRequest()->expectsJson();
    }
}
