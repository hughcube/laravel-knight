<?php

namespace HughCube\Laravel\Knight\Routing;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use HughCube\GuzzleHttp\HttpClientTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * @mixin Action
 */
trait ProxyForwarder
{
    use HttpClientTrait;

    /**
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    protected function action(): ResponseInterface
    {
        $response = $this->getHttpClient()->request(
            $this->getProxyMethod(),
            $this->getProxyUrl(),
            $this->getProxyRequestOptions()
        );

        foreach ($this->getIgnoreProxyHeaders() as $name) {
            $response = $response->withoutHeader($name);
        }

        return $response;
    }

    /**
     * @return string
     */
    abstract protected function getProxyUrl(): string;

    /**
     * @return string
     */
    protected function getProxyMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * @return array
     */
    protected function getProxyRequestOptions(): array
    {
        return [
            RequestOptions::BODY => $this->getProxyBody(),
            RequestOptions::QUERY => $this->getProxyQuery(),
            RequestOptions::PROXY => $this->getProxyProxy(),
            RequestOptions::STREAM => $this->getProxyStream(),
            RequestOptions::VERIFY => $this->getProxyVerify(),
            RequestOptions::HEADERS => $this->getProxyHeaders(),
            RequestOptions::HTTP_ERRORS => $this->getProxyHttpErrors(),
            RequestOptions::ALLOW_REDIRECTS => $this->getProxyAllowRedirects(),
        ];
    }

    /**
     * @return false|resource|string|null
     */
    protected function getProxyBody()
    {
        return $this->getRequest()->getContent();
    }

    protected function getProxyProxy(): ?string
    {
        return null;
    }

    protected function getProxyHttpErrors(): bool
    {
        return true;
    }

    protected function getProxyVerify(): bool
    {
        return false;
    }

    /**
     * @return mixed
     */
    protected function getProxyAllowRedirects()
    {
        return false;
    }

    /**
     * @return array
     */
    protected function getProxyQuery(): array
    {
        return $this->getRequest()->query->all();
    }

    protected function getProxyStream(): bool
    {
        return false;
    }

    /**
     * @return array
     */
    protected function getProxyHeaders(): array
    {
        $headers = $this->getRequest()->headers->all();

        foreach ($headers as $key => $values) {
            if ($this->isIgnoreProxyHeader($key)) {
                unset($headers[$key]);
            }
        }

        return $headers;
    }

    protected function getIgnoreProxyHeaders(): array
    {
        return ['host', 'content-length', 'transfer-encoding', 'connection'];
    }

    /**
     * @param  string  $name
     * @return bool
     */
    protected function isIgnoreProxyHeader(string $name): bool
    {
        return in_array(strtolower($name), $this->getIgnoreProxyHeaders());
    }
}
