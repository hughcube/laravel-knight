<?php

namespace HughCube\Laravel\Knight\Testing\Traits;

use Illuminate\Support\Facades\Http;

trait MockHttpTrait
{
    /**
     * 注册模拟 HTTP 响应.
     *
     * @param  string  $url
     * @param  int  $status
     * @param  array  $headers
     * @param  string  $body
     * @return void
     */
    protected function mockHttpResponse(string $url, int $status = 200, array $headers = [], string $body = ''): void
    {
        Http::fake([
            $url => Http::response($body, $status, $headers),
        ]);
    }

    /**
     * 注册模拟 HTTP JSON 响应.
     *
     * @param  string  $url
     * @param  array  $data
     * @param  int  $status
     * @return void
     */
    protected function mockHttpJsonResponse(string $url, array $data, int $status = 200): void
    {
        Http::fake([
            $url => Http::response($data, $status, ['Content-Type' => 'application/json']),
        ]);
    }

    /**
     * 获取所有记录的 HTTP 请求
     *
     * @return array
     */
    protected function getRecordedHttpRequests(): array
    {
        $recorded = Http::recorded();

        return $recorded instanceof \Illuminate\Support\Collection ? $recorded->all() : (array) $recorded;
    }
}
