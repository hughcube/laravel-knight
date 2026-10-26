<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use Carbon\Carbon;
use Exception;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\Laravel\Knight\Queue\Job;
use HughCube\PUrl\Url as PUrl;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class PingJob extends Job
{
    use HttpClientTrait;

    public function rules(): array
    {
        return [
            'url' => ['string', 'nullable'],
            'method' => ['string', 'nullable'],
            'options' => ['array', 'nullable'],
        ];
    }

    /**
     * @throws Exception
     */
    protected function action(): void
    {
        $url = $this->getUrl();
        $method = strtoupper($this->p('method', 'GET'));
        $options = array_merge(['timeout' => 2.0, 'http_errors' => false], $this->p('options', []));

        $response = null;
        $exception = null;

        $start = Carbon::now();

        try {
            $response = $this->getHttpClient()->request($method, $url, $options);
        } catch (Throwable $exception) {
        }
        $end = Carbon::now();

        $requestId = $this->getRequestId($response);
        $duration = $start->diffInMilliseconds($end);
        $statusCode = $response instanceof Response ? $response->getStatusCode() : null;
        $exception = $exception instanceof Throwable ? $exception->getMessage() : null;

        $this->info(sprintf(
            'method: %s, url: %s, status: %s, duration: %sms requestId: %s, exception: %s',
            $method,
            $url,
            $statusCode,
            $duration,
            $requestId,
            $exception
        ));
    }

    protected function getRequestId($response): ?string
    {
        if (! $response instanceof Response) {
            return null;
        }

        foreach ($response->getHeaders() as $name => $header) {
            if (Str::endsWith(strtolower($name), 'request-id')) {
                return $response->getHeaderLine($name);
            }
        }

        return null;
    }

    protected function getUrl(): string
    {
        $url = $this->p()->get('url', 'knight.ping');
        if (is_string($url) && PUrl::isUrlString($url)) {
            return $url;
        }

        $url = Route::has($url) ? route($url) : URL::to($url);

        /** parse url */
        $purl = PUrl::parse($url);

        /** with app url scheme */
        $appUrl = PUrl::parse(config('app.url'));
        if ($appUrl instanceof PUrl && ! empty($appUrl->getScheme()) && $purl instanceof PUrl) {
            $purl = $purl->withScheme($appUrl->getScheme());
        }

        return $purl instanceof PUrl ? $purl->toString() : $url;
    }
}
