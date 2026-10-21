<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/2/6
 * Time: 16:29.
 */

namespace HughCube\Laravel\Knight\Http;

use HughCube\Laravel\Knight\Support\Version;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * @deprecated Support in octane is not very good, try to use mixin implementation.
 */
class Request extends HttpRequest
{
    /** @var mixed */
    protected $userAgentDetect = null;

    protected array $methodResultCache = [];

    protected function prepareBaseUrl(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::prepareBaseUrl();
    }

    protected function prepareRequestUri(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::prepareRequestUri();
    }

    protected function preparePathInfo(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::preparePathInfo();
    }

    public function isFromTrustedProxy(): bool
    {
        return $this->methodResultCache[__METHOD__] ??= parent::isFromTrustedProxy();
    }

    public function isSecure(): bool
    {
        return $this->methodResultCache[__METHOD__] ??= parent::isSecure();
    }

    public function getHost(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::getHost();
    }

    public function getPort(): ?int
    {
        return $this->methodResultCache[__METHOD__] ??= (intval(parent::getPort()) ?: null);
    }

    public function getQueryString(): ?string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::getQueryString();
    }

    public function getMethod(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::getMethod();
    }

    public function getBaseUrl(): string
    {
        return $this->methodResultCache[__METHOD__] ??= parent::getBaseUrl();
    }

    public function getClientHeaderPrefix(): string
    {
        return '';
    }

    public function getClientVersion(): ?string
    {
        return $this->headers->get(
            sprintf('%sVersion', $this->getClientHeaderPrefix())
        );
    }

    public function getClientNonce(): ?string
    {
        return $this->headers->get(
            sprintf('%sNonce', $this->getClientHeaderPrefix())
        );
    }

    public function getClientSignature(): ?string
    {
        return $this->headers->get(
            sprintf('%sSignature', $this->getClientHeaderPrefix())
        );
    }

    public function getClientHeaders(): HeaderBag
    {
        $headers = [];

        foreach ($this->headers as $name => $values) {
            if (0 === strripos($name, $this->getClientHeaderPrefix())) {
                $headers[$name] = $values;
            }
        }

        return new HeaderBag($headers);
    }

    /**
     * 获取客户端日期
     */
    public function getDate(): ?string
    {
        return $this->headers->get('Date');
    }

    public function getClientDate(): ?string
    {
        return $this->headers->get(
            sprintf('%sDate', $this->getClientHeaderPrefix())
        );
    }

    /**
     * 获取agent检测.
     * @return mixed
     */
    public function getUserAgentDetect()
    {
        if (null !== $this->userAgentDetect) {
            return $this->userAgentDetect;
        }

        if (class_exists($class = 'Jenssegers\Agent\Agent')) {
            return $this->userAgentDetect = new $class($this->headers->all(), $this->userAgent());
        }

        throw new \BadMethodCallException(
            'Package "jenssegers/agent" is not installed. Please install it or use lightweight detection methods.'
        );
    }

    /**
     * 判断是否在微信客户端内.
     */
    public function isWeChat(): bool
    {
        return false !== strripos($this->userAgent(), 'MicroMessenger');
    }

    /**
     * 判断是否在微信客户端内.
     */
    public function isWeChatMiniProgram(): bool
    {
        return $this->isWeChat() && false !== strripos($this->userAgent(), 'miniProgram');
    }

    /**
     * 判断是否在postmen.
     */
    public function isPostmen(): bool
    {
        return 0 === strripos($this->userAgent(), 'PostmanRuntime');
    }

    /**
     * 判断请求是否来自指定版本的客户端.
     *
     * 1.0(client) == 1.0 => true
     */
    public function isEqClientVersion(string $version, ?int $length = null): ?bool
    {
        if (null === ($clientVersion = $this->getClientVersion())) {
            return null;
        }

        return Version::compare('=', $clientVersion, $version, $length);
    }

    /**
     * 判断请求是否来自大于指定版本的客户端.
     *
     * 2.0(client) > 1.0 => true
     */
    public function isLtClientVersion(string $version, bool $contain = false, ?int $length = null): ?bool
    {
        if (null === ($clientVersion = $this->getClientVersion())) {
            return null;
        }

        return Version::compare($contain ? '>=' : '>', $clientVersion, $version, $length);
    }

    /**
     * 判断请求是否来自小于指定版本的客户端.
     *
     * 1.0(client) < 2.0 => true
     */
    public function isGtClientVersion(string $version, bool $contain = false, ?int $length = null): ?bool
    {
        if (null === ($clientVersion = $this->getClientVersion())) {
            return null;
        }

        return Version::compare($contain ? '<=' : '<', $clientVersion, $version, $length);
    }

    /**
     * 获取最后一级目录.
     */
    public function getLastDirectory(): ?string
    {
        return Str::afterLast(trim($this->getPathInfo()), '/') ?: null;
    }
}
