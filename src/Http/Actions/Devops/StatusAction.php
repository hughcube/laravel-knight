<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StatusAction extends Controller
{
    /**
     * @return array<string, array<string>>
     */
    protected function rules(): array
    {
        return [
            'x-format' => ['nullable', 'string'],
        ];
    }

    /**
     * @return Response
     */
    protected function action(): Response
    {
        $checks = [];
        $allHealthy = true;

        $checks['Database'] = $this->checkDatabase();
        $checks['Cache'] = $this->checkRedis();

        foreach ($checks as $check) {
            if ('error' === $check['status']) {
                $allHealthy = false;
            }
        }

        $data = [
            'healthy' => $allHealthy ? 1 : 0,
            'checks' => $checks,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        if ('json' === $this->p('x-format') || $this->getRequest()->expectsJson()) {
            return $this->asSuccess($data);
        }

        return new Response($this->renderHtml($data), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param  array{healthy: int, checks: array<string, array{status: string, latency_ms?: float, message?: string}>, timestamp: string}  $data
     */
    private function renderHtml(array $data): string
    {
        $appName = config('app.name', 'App');
        $env = config('app.env', 'unknown');
        $healthy = 1 === $data['healthy'];
        $timestamp = $data['timestamp'];
        $checksHtml = '';

        foreach ($data['checks'] as $name => $check) {
            $status = $check['status'];
            if ('ok' === $status) {
                $dot = '🟢';
            } elseif ('skipped' === $status) {
                $dot = '🟡';
            } else {
                $dot = '🔴';
            }

            $latency = isset($check['latency_ms']) ? "{$check['latency_ms']} ms" : '-';
            $detail = 'ok' === $status ? $latency : htmlspecialchars($check['message'] ?? $latency, ENT_QUOTES, 'UTF-8');
            $statusText = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
            $checksHtml .= "<tr><td>{$dot} {$name}</td><td>{$statusText}</td><td>{$detail}</td></tr>";
        }

        $overallColor = $healthy ? '#22c55e' : '#ef4444';
        $overallText = $healthy ? 'All Systems Operational' : 'Service Degraded';

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>系统状态 - {$appName} ({$env})</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #1e293b; border-radius: 12px; padding: 32px; width: 100%; max-width: 520px; box-shadow: 0 4px 24px rgba(0,0,0,.3); margin: 20px; }
  .header { text-align: center; margin-bottom: 24px; }
  .header h1 { font-size: 18px; font-weight: 600; color: #94a3b8; margin-bottom: 12px; }
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; color: #fff; background: {$overallColor}; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { text-align: left; padding: 8px 0; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid #334155; }
  td { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #1e293b; }
  tr:last-child td { border-bottom: none; }
  td:nth-child(2) { text-align: center; }
  td:nth-child(3) { text-align: right; color: #94a3b8; }
  .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #475569; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>{$appName} 状态 · {$env}</h1>
    <span class="status-badge">{$overallText}</span>
  </div>
  <table>
    <tr><th>服务</th><th>状态</th><th>延迟</th></tr>
    {$checksHtml}
  </table>
  <div class="footer">最后检查: {$timestamp}</div>
</div>
</body>
</html>
HTML;
    }

    /**
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $default = config('database.default');
            if (empty($default)) {
                return ['status' => 'skipped', 'message' => 'No default database connection configured'];
            }

            $start = microtime(true);
            DB::connection($default)->selectOne('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    private function checkRedis(): array
    {
        if (! extension_loaded('redis') && ! class_exists(\Predis\Client::class)) {
            return ['status' => 'skipped', 'message' => 'Redis extension not loaded'];
        }

        try {
            $start = microtime(true);
            Redis::connection()->command('ping');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
