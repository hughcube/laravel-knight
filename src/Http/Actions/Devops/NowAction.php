<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * 高精度时钟校准与 RFC3339 探针.
 *
 * 推荐路由配置：
 * Route::any('/now', NowAction::class)->middleware([VerifyDevopsToken::class]);
 *
 * 推荐中间件组合：
 * - 默认建议：[VerifyDevopsToken::class]
 * - 或公开：无中间件 (供跨端客户端毫秒级时钟校准与时区同步)
 */
class NowAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        $now = Carbon::now();

        return $this->asSuccess([
            /** @phpstan-ignore-next-line */
            'timestamp' => $now->getTimestampAsFloat(),
            /** @phpstan-ignore-next-line */
            'rfc3339' => $now->toRfc3339ExtendedString(),
        ]);
    }
}
