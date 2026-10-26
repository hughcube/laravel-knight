<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use HughCube\Laravel\Knight\Queue\Jobs\BatchPingJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\PUrl\Url as PUrl;
use Illuminate\Support\Facades\URL;

class BatchPingJobTest extends TestCase
{
    public function testRun()
    {
        $this->assertJob(BatchPingJob::new([
            'jobs' => [
                ['url' => null],
                ['url' => 'https://www1111111111.baidu.com/'],
            ],
        ]));
    }

    public function testParseRequestIdReadsHeader()
    {
        $job = new BatchPingJob(['jobs' => [['url' => 'https://example.test']]]);
        $response = new Response(200, ['X-Request-Id' => 'batch-1']);

        $this->assertSame('batch-1', $this->callMethod($job, 'parseRequestId', [$response]));
        $this->assertNull($this->callMethod($job, 'parseRequestId', [null]));
    }

    public function testParseUrlReturnsAbsoluteUrl()
    {
        $job = new BatchPingJob(['jobs' => [['url' => 'https://example.test/ping']]]);

        $url = $this->callMethod($job, 'parseUrl', ['https://example.test/ping']);

        $this->assertSame('https://example.test/ping', $url);
    }

    public function testParseUrlUsesAppSchemeForRelativePath()
    {
        $job = new BatchPingJob(['jobs' => [['url' => 'health']]]);

        config(['app.url' => 'https://example.test']);
        URL::forceRootUrl('http://127.0.0.1');

        try {
            $url = $this->callMethod($job, 'parseUrl', ['health']);
            $parsed = PUrl::parse($url);

            $this->assertInstanceOf(PUrl::class, $parsed);
            $this->assertSame('https', $parsed->getScheme());
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function testMakeRequestsYieldsRequestObjects()
    {
        $job = new BatchPingJob(['jobs' => [['url' => 'https://example.test']]]);
        $requests = [
            [
                'method' => 'POST',
                'url' => 'https://example.test/ping',
                'headers' => ['X-Test' => '1'],
            ],
        ];

        $generator = $this->callMethod($job, 'makeRequests', [$requests]);
        $items = iterator_to_array($generator);

        $this->assertCount(1, $items);
        $this->assertInstanceOf(PsrRequest::class, $items[0]);
        $this->assertSame('POST', $items[0]->getMethod());
        $this->assertSame('/ping', $items[0]->getUri()->getPath());
    }

    public function testLogResponseUsesRequestId()
    {
        $job = new class() extends BatchPingJob
        {
            public array $messages = [];

            public function info(string $message, array $context = []): void
            {
                $this->messages[] = $message;
            }
        };

        $response = new Response(200, ['X-Request-Id' => 'req-42']);

        $this->callMethod($job, 'logResponse', ['GET', 'https://example.test', 12, $response]);

        $this->assertNotEmpty($job->messages);
        $this->assertStringContainsString('status: 200', $job->messages[0]);
        $this->assertStringContainsString('requestId: req-42', $job->messages[0]);
    }
}
