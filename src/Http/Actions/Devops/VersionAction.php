<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * 部署版本与构建时间探针.
 *
 * 推荐路由配置：
 * Route::any('/version', VersionAction::class);
 *
 * 推荐中间件组合：
 * - 默认建议：无中间件 (供发布流水线/CI/CD 部署冒烟校验)
 * - 敏感环境：[VerifyDevopsToken::class]
 *
 * 版本获取逻辑：
 * - 优先读取项目根目录下的 version.json 文件
 * - 兜底回退读取环境变量 APP_BUILD_VERSION 与 APP_BUILD_TIME
 */
class VersionAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        $file = base_path('version.json');
        $version = [];

        if (is_file($file) && false !== ($content = @file_get_contents($file))) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $version = $decoded;
            }
        }

        return $this->asSuccess([
            'build_version' => $version['build_version'] ?? env('APP_BUILD_VERSION', 'dev'),
            'build_time'    => $version['build_time'] ?? env('APP_BUILD_TIME'),
        ]);
    }
}
