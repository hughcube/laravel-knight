<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/30
 * Time: 16:07.
 */

namespace HughCube\Laravel\Knight\Mixin\Http;

use Closure;
use HughCube\Laravel\Knight\Http\Request as KnightRequest;
use HughCube\Laravel\Knight\Support\Version;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * @mixin-target \Illuminate\Http\Request
 *
 * @method null|string getClientHeaderPrefix()
 *
 * @see KnightRequest
 */
class RequestMixin
{
    /**
     * 获取客户端版本.
     */
    public function getClientVersion(): Closure
    {
        return function (): ?string {
            /** @phpstan-ignore-next-line */
            return $this->headers->get(sprintf('%sVersion', $this->getClientHeaderPrefix()));
        };
    }

    /**
     * 获取客户端的随机字符串.
     */
    public function getClientNonce(): Closure
    {
        return function (): ?string {
            /** @phpstan-ignore-next-line */
            return $this->headers->get(sprintf('%sNonce', $this->getClientHeaderPrefix()));
        };
    }

    /**
     * 获取客户端的签名字符串.
     */
    public function getClientSignature(): Closure
    {
        return function (): ?string {
            /** @phpstan-ignore-next-line */
            return $this->headers->get(sprintf('%sSignature', $this->getClientHeaderPrefix()));
        };
    }

    /**
     * 获取客户端的所有请求头.
     */
    public function getClientHeaders(): Closure
    {
        return function (): HeaderBag {
            /** @phpstan-ignore-next-line */
            $headers = array_filter($this->headers->all() ?: [], function ($name) {
                return Str::startsWith(strtolower($name), strtolower($this->getClientHeaderPrefix()));
            }, ARRAY_FILTER_USE_KEY);

            return new HeaderBag($headers);
        };
    }

    /**
     * 获取客户端日期
     */
    public function getDate(): Closure
    {
        return function (): ?string {
            /** @phpstan-ignore-next-line */
            return $this->headers->get('Date');
        };
    }

    public function getClientDate(): Closure
    {
        return function (): ?string {
            /** @phpstan-ignore-next-line */
            return $this->headers->get(sprintf('%sDate', $this->getClientHeaderPrefix()));
        };
    }

    /**
     * 获取agent检测.
     */
    public function getUserAgentDetect(): Closure
    {
        return function () {
            $key = '__knight_user_agent_detect_2f8a9c3e';

            if ($this->attributes->has($key)) {
                return $this->attributes->get($key);
            }

            if (class_exists($class = 'Jenssegers\Agent\Agent')) {
                $agent = new $class($this->headers->all(), $this->userAgent());
                $this->attributes->set($key, $agent);
                return $agent;
            }

            throw new \BadMethodCallException(
                'Package "jenssegers/agent" is not installed. Please install it or use lightweight detection methods.'
            );
        };
    }

    /**
     * 判断是否在微信客户端内.
     */
    public function isWeChat(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'MicroMessenger');
        };
    }

    /**
     * 判断是否在微信客户端内.
     */
    public function isWeChatMiniProgram(): Closure
    {
        return function (): bool {
            /** @phpstan-ignore-next-line */
            return $this->isWeChat() && str_contains($this->userAgent(), 'miniProgram');
        };
    }

    /**
     * 判断是否在postmen/Apifox.
     */
    public function isPostmen(): Closure
    {
        return function (): bool {
            return Str::startsWith($this->userAgent(), ['PostmanRuntime']);
        };
    }

    /**
     * 判断是否为 API 调试工具.
     */
    public function isApiDebugTool(): Closure
    {
        return function (): bool {
            return Str::startsWith($this->userAgent(), [
                'PostmanRuntime',
                'Apifox',
                'insomnia',
                'HTTPie',
                'curl',
                'Hoppscotch',
                'ApiPOST',
                'Paw',
                'RapidAPI',
            ]);
        };
    }

    /**
     * 判断请求是否来自指定版本的客户端.
     *
     * 1.0(client) == 1.0 => true
     */
    public function isEqClientVersion(): Closure
    {
        return function (string $version, ?int $length = null): bool {
            /** @phpstan-ignore-next-line */
            return Version::compare('=', $this->getClientVersion(), $version, $length);
        };
    }

    /**
     * 判断请求是否来自大于指定版本的客户端.
     *
     * 2.0(client) > 1.0 => true
     */
    public function isLtClientVersion(): Closure
    {
        return function (string $version, bool $contain = false, ?int $length = null): bool {
            /** @phpstan-ignore-next-line */
            return Version::compare($contain ? '>=' : '>', $this->getClientVersion(), $version, $length);
        };
    }

    /**
     * 判断请求是否来自小于指定版本的客户端.
     *
     * 1.0(client) < 2.0 => true
     */
    public function isGtClientVersion(): Closure
    {
        return function (string $version, bool $contain = false, ?int $length = null): bool {
            /** @phpstan-ignore-next-line */
            return Version::compare($contain ? '<=' : '<', $this->getClientVersion(), $version, $length);
        };
    }

    /**
     * 获取最后一级目录.
     */
    public function getLastDirectory(): Closure
    {
        return function (): ?string {
            return Str::afterLast(trim($this->getPathInfo()), '/') ?: null;
        };
    }

    /**
     * 判断是否在企业微信客户端内.
     */
    public function isWeCom(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'wxwork');
        };
    }

    /**
     * 判断是否在钉钉客户端内.
     */
    public function isDingTalk(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'DingTalk');
        };
    }

    /**
     * 判断是否在飞书客户端内.
     */
    public function isFeishu(): Closure
    {
        return function (): bool {
            $ua = $this->userAgent();

            return str_contains($ua, 'Lark') || str_contains($ua, 'Feishu');
        };
    }

    /**
     * 判断是否在支付宝客户端内.
     */
    public function isAlipay(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'AlipayClient');
        };
    }

    /**
     * 判断是否在QQ客户端内(非QQ浏览器).
     */
    public function isQQ(): Closure
    {
        return function (): bool {
            $ua = $this->userAgent();

            return str_contains($ua, 'QQ/') && !str_contains($ua, 'MQQBrowser');
        };
    }

    /**
     * 判断是否在QQ浏览器内.
     */
    public function isQQBrowser(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'MQQBrowser');
        };
    }

    /**
     * 判断是否在UC浏览器内.
     */
    public function isUCBrowser(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'UCBrowser');
        };
    }

    /**
     * 判断是否在微博客户端内.
     */
    public function isWeibo(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'Weibo');
        };
    }

    /**
     * 判断是否在抖音/字节系客户端内.
     */
    public function isDouyin(): Closure
    {
        return function (): bool {
            $ua = $this->userAgent();

            return str_contains($ua, 'Aweme') || str_contains($ua, 'BytedanceWebview');
        };
    }

    /**
     * 判断是否在Quark浏览器内.
     */
    public function isQuark(): Closure
    {
        return function (): bool {
            return str_contains($this->userAgent(), 'Quark');
        };
    }
}
