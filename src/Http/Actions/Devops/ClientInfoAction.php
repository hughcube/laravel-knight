<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 客户端信息检测与环境诊断
 * - 纯原生无状态解析：彻底剥离停更的 Agent 依赖，杜绝每次请求的对象反射与性能损耗
 * - 支持 JSON 结构化与优雅的暗黑风格 HTML 双模式渲染
 */
class ClientInfoAction extends Controller
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

    protected function action(): Response
    {
        $request = $this->getRequest();
        $ua = strval($request->userAgent() ?? '');
        $clientIp = strval($request->getClientIp() ?? '127.0.0.1');

        $isMobile = boolval(preg_match('/(android|iphone|ipod|mobile|phone)/i', $ua));
        $isTablet = boolval(preg_match('/(ipad|tablet|(android(?!.*mobile)))/i', $ua));
        $isRobot = boolval(preg_match('/(bot|spider|crawl|slurp|curl|wget|python|httpclient)/i', $ua));

        $os = $this->detectOperatingSystem($ua);
        $browser = $this->detectBrowser($ua);

        $items = [
            '网络与请求' => [
                '客户端 IP'       => $clientIp,
                'User-Agent'   => $ua ?: null,
                '是否安全传输 (HTTPS)' => $request->isSecure() ? '是' : '否',
                '是否内网访问'      => Str::isPrivateIp($clientIp) ? '是' : '否',
            ],
            '客户端类型识别' => [
                '是否移动端'       => $isMobile ? '是' : '否',
                '是否平板'        => $isTablet ? '是' : '否',
                '是否爬虫/脚本'     => $isRobot ? '是' : '否',
                '是否微信'        => method_exists($request, 'isWeChat') && $request->isWeChat() ? '是' : '否',
                '是否微信小程序'     => method_exists($request, 'isWeChatMiniProgram') && $request->isWeChatMiniProgram() ? '是' : '否',
                '是否企业微信'      => method_exists($request, 'isWeCom') && $request->isWeCom() ? '是' : '否',
                '是否钉钉'        => method_exists($request, 'isDingTalk') && $request->isDingTalk() ? '是' : '否',
                '是否飞书'        => method_exists($request, 'isFeishu') && $request->isFeishu() ? '是' : '否',
                '是否支付宝'       => method_exists($request, 'isAlipay') && $request->isAlipay() ? '是' : '否',
                '是否 API 调试工具'  => method_exists($request, 'isApiDebugTool') && $request->isApiDebugTool() ? '是' : '否',
            ],
            '操作系统与内核' => [
                '操作系统' => $os['name'],
                '系统版本' => $os['version'],
            ],
            '浏览器与应用' => [
                '浏览器/应用' => $browser['name'],
                '应用版本'   => $browser['version'],
            ],
        ];

        if ('json' === strtolower(strval($this->p('x-format'))) || $request->expectsJson()) {
            return $this->asSuccess($items);
        }

        return new Response($this->renderHtml($items), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 轻量高效解析操作系统
     * @return array{name: string|null, version: string|null}
     */
    protected function detectOperatingSystem(string $ua): array
    {
        if (empty($ua)) {
            return ['name' => null, 'version' => null];
        }

        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $matches)) {
            $verMap = ['10.0' => '10 / 11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            return ['name' => 'Windows', 'version' => $verMap[$matches[1]] ?? $matches[1]];
        }

        if (preg_match('/Mac OS X ([\d_]+)/i', $ua, $matches)) {
            return ['name' => 'macOS', 'version' => str_replace('_', '.', $matches[1])];
        }

        if (preg_match('/Android ([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Android', 'version' => $matches[1]];
        }

        if (preg_match('/(?:iPhone|iPad|iPod).*?OS ([\d_]+)/i', $ua, $matches)) {
            return ['name' => 'iOS', 'version' => str_replace('_', '.', $matches[1])];
        }

        if (preg_match('/Linux/i', $ua)) {
            return ['name' => 'Linux', 'version' => null];
        }

        return ['name' => null, 'version' => null];
    }

    /**
     * 轻量高效解析浏览器或 App
     * @return array{name: string|null, version: string|null}
     */
    protected function detectBrowser(string $ua): array
    {
        if (empty($ua)) {
            return ['name' => null, 'version' => null];
        }

        if (preg_match('/MicroMessenger\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'WeChat', 'version' => $matches[1]];
        }

        if (preg_match('/DingTalk\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'DingTalk', 'version' => $matches[1]];
        }

        if (preg_match('/Feishu\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Feishu', 'version' => $matches[1]];
        }

        if (preg_match('/AlipayClient\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Alipay', 'version' => $matches[1]];
        }

        if (preg_match('/Edg(?:e)?\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Edge', 'version' => $matches[1]];
        }

        if (preg_match('/Chrome\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Chrome', 'version' => $matches[1]];
        }

        if (preg_match('/Version\/([\d.]+).*?Safari/i', $ua, $matches)) {
            return ['name' => 'Safari', 'version' => $matches[1]];
        }

        if (preg_match('/Firefox\/([\d.]+)/i', $ua, $matches)) {
            return ['name' => 'Firefox', 'version' => $matches[1]];
        }

        return ['name' => null, 'version' => null];
    }

    /**
     * 渲染暗黑极客风格的诊断视图
     * @param array<string, array<string, string|null>> $items
     */
    protected function renderHtml(array $items): string
    {
        $body = '';
        foreach ($items as $group => $fields) {
            $body .= '<h3>' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</h3><table>';
            foreach ($fields as $label => $value) {
                $displayValue = null !== $value && '' !== $value
                    ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                    : '<span style="color:#64748b">-</span>';
                $body .= '<tr><td class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td><td>' . $displayValue . '</td></tr>';
            }
            $body .= '</table>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客户端信息检测</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; padding: 24px 16px; min-height: 100vh; }
        .container { max-width: 820px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 28px; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
        h2 { color: #f8fafc; font-size: 20px; border-bottom: 2px solid #38bdf8; padding-bottom: 12px; margin-bottom: 20px; }
        h3 { color: #38bdf8; margin: 20px 0 10px; font-size: 15px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; background: #0f172a; border-radius: 8px; overflow: hidden; margin-bottom: 16px; border: 1px solid #334155; }
        td { padding: 10px 16px; font-size: 13px; border-bottom: 1px solid #1e293b; word-break: break-all; }
        td.label { width: 180px; color: #94a3b8; font-weight: 500; background: #182234; border-right: 1px solid #334155; }
        tr:last-child td { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>客户端信息识别</h2>
        {$body}
    </div>
</body>
</html>
HTML;
    }
}
