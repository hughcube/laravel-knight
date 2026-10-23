<?php

namespace HughCube\Laravel\Knight\Scheduling;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * 统一调度触发网关 Action (/devops/schedule).
 *
 * 供 Docker Compose 伴生 pulse 容器或外部定时探针发起心跳调用。
 *
 * 安全护栏：
 * 1. 随机 Token 鉴权 (匹配 KNIGHT_DEVOPS_SCHEDULE_TOKEN)
 * 2. 内网 IP 白名单防护 (放行 127.0.0.1 及 172.x / 10.x / 192.168.x 内网)
 * 3. 分钟级幂等锁 (防重放与高频并发刷接口)
 */
class DevopsScheduleAction extends Controller
{
    /**
     * @return Response
     * @throws Throwable
     */
    protected function action(): Response
    {
        // 1. 安全防御门禁
        $this->verifySecurity();

        // 2. 分钟级幂等防重锁 (60秒内仅允许触发1次)
        $minute = Carbon::now()->format('YmdHi');
        $lockKey = sprintf('knight:devops:schedule:lock:%s', $minute);

        $ttl = 55;
        if (!Cache::add($lockKey, 1, $ttl)) {
            return $this->asSuccess([
                'triggered_minute' => $minute,
                'status'           => 'skipped',
                'reason'           => 'Already triggered in this minute',
            ]);
        }

        // 3. 执行大一统 ScheduleJob
        $job = new ScheduleJob();
        $job->handle();
        $report = $job->getReport();

        return $this->asSuccess(array_merge([
            'triggered_minute' => $minute,
            'status'           => 'done',
        ], $report));
    }

    /**
     * 校验安全鉴权 (Token 校验或内网 IP 白名单).
     *
     * @throws AccessDeniedHttpException
     */
    protected function verifySecurity(): void
    {
        $request = $this->getRequest();

        // A. 校验配置的 DevOps Token (支持 Header / Bearer / Query)
        $expectedToken = env('KNIGHT_DEVOPS_SCHEDULE_TOKEN')
            ?: config('knight.schedule.token')
            ?: config('knight.devops.schedule_token');

        if (!empty($expectedToken)) {
            $clientToken = $request->header('X-Devops-Token')
                ?: $request->bearerToken()
                ?: $request->query('token');

            if (!empty($clientToken) && hash_equals((string) $expectedToken, (string) $clientToken)) {
                return; // Token 校验通过
            }
        }

        // B. 校验客户端来源 IP 是否为内网白名单
        $ip = (string) $request->ip();
        if ($this->isIntranetIp($ip)) {
            return; // 内网 IP 放行
        }

        throw new AccessDeniedHttpException('DevOps schedule access denied.');
    }

    /**
     * 判断是否为内网或本机 IP.
     */
    protected function isIntranetIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return Str::startsWith($ip, ['172.', '10.', '192.168.']);
    }
}
