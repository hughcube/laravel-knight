<?php

namespace HughCube\Laravel\Knight\OPcache\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Queue\Job;
use HughCube\Laravel\Knight\Traits\Container;
use HughCube\PUrl\Url as PUrl;
use Psr\SimpleCache\InvalidArgumentException;

class WatchOpcacheScriptsJob extends Job
{
    use Container;
    use HttpClientTrait;

    public function rules(): array
    {
        return [
            'url' => ['string', 'nullable'],
            'use_app_url' => ['boolean', 'default:1'],
            'timeout' => ['integer', 'default:30'],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function action(): void
    {
        /** 确定请求的url */
        $url = OPcache::i()->getUrl($this->p()->get('url') ?: null);
        if (! $url instanceof PUrl) {
            $this->warning('Remote interface URL cannot be found!');

            return;
        }

        /** 获取到远程的脚本列表 */
        try {
            $scripts = OPcache::i()->getRemoteScripts(
                $url,
                floatval($this->p()->get('timeout')),
                $this->p()->getBoolean('use_app_url')
            );
        } catch (GuzzleException $exception) {
            $this->warning(sprintf('http error: %s!', $exception->getMessage()));

            return;
        }

        /** log记录 */
        $this->info(sprintf('watch OPcache files, url: %s, count: %s.', $url->toString(), count($scripts)));
    }
}
