<?php

namespace HughCube\Laravel\Knight\Tests\OPcache;

use GuzzleHttp\RequestOptions;
use HughCube\GuzzleHttp\Client as HttpClient;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\PUrl\Url as PUrl;
use Illuminate\Support\Facades\Cache;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

class OPcacheTest extends TestCase
{
    public function testGetPHPParserStmtClasses()
    {
        $stmts = [
            new Class_(new Identifier('RootClass')),
            new Namespace_(new Name('App\\Sub'), [
                new Class_(new Identifier('NamespacedClass')),
            ]),
        ];

        $classes = OPcache::i()->getPHPParserStmtClasses($stmts)->toArray();

        $this->assertSame(['RootClass', 'App\\Sub\\NamespacedClass'], $classes);
    }

    public function testGetUrlReplacesLocalhostWithAppUrl()
    {
        config(['app.url' => 'https://example.test:8443']);

        $url = OPcache::i()->getUrl('http://127.0.0.1/health', true);

        $this->assertSame('example.test', $url->getHost());
        $this->assertSame(8443, $url->getPort());
        $this->assertSame('/health', $url->getPath());
    }

    public function testCacheKeyAndHistoryScripts()
    {
        config([
            'cache.default' => 'array',
            'cache.stores.array' => ['driver' => 'array'],
        ]);

        $opcache = OPcache::i();

        $key = self::callMethod($opcache, 'getCacheKey');
        Cache::store()->set($key, ['foo' => 123]);

        $history = self::callMethod($opcache, 'getHistoryScripts');

        $this->assertSame(['foo' => 123], $history);
    }

    public function testGetScriptsFiltersOldEntriesAndCaches()
    {
        config([
            'cache.default' => 'array',
            'cache.stores.array' => ['driver' => 'array'],
        ]);

        $now = time();
        $opcache = new class($now) extends OPcache
        {
            private int $now;

            public function __construct(int $now)
            {
                $this->now = $now;
            }

            protected function loadedOPcacheExtension()
            {
            }

            protected function getHistoryScripts(): array
            {
                return [
                    'old.php' => $this->now - (181 * 24 * 3600),
                    'recent.php' => $this->now - 60,
                ];
            }

            protected function getOpCacheScripts(): array
            {
                return [
                    'current.php' => $this->now,
                ];
            }
        };

        $scripts = $opcache->getScripts();

        $this->assertArrayHasKey('recent.php', $scripts);
        $this->assertArrayHasKey('current.php', $scripts);
        $this->assertArrayNotHasKey('old.php', $scripts);

        $key = self::callMethod($opcache, 'getCacheKey');
        $this->assertSame($scripts, Cache::store()->get($key));
    }

    public function testGetRemoteScriptsUsesHttpClient()
    {
        $client = new class() extends HttpClient
        {
            public array $requests = [];

            public function get($url, array $options)
            {
                $this->requests[] = [$url, $options];

                return new class()
                {
                    public function getBody()
                    {
                        return new class()
                        {
                            public function getContents()
                            {
                                return json_encode(['Data' => ['scripts' => ['a.php', 'b.php']]]);
                            }
                        };
                    }
                };
            }
        };

        $opcache = new class($client) extends OPcache
        {
            private $client;

            public function __construct($client)
            {
                $this->client = $client;
            }

            protected function getHttpClient(): HttpClient
            {
                return $this->client;
            }

            public function getUrl($url = null, $useAppHost = true): ?PUrl
            {
                return PUrl::parse('https://example.test/opcache/scripts');
            }
        };

        $scripts = $opcache->getRemoteScripts(null, 3.5);

        $this->assertSame(['a.php', 'b.php'], $scripts);
        $this->assertCount(1, $client->requests);
        $this->assertInstanceOf(PUrl::class, $client->requests[0][0]);
        $this->assertSame(3.5, $client->requests[0][1][RequestOptions::TIMEOUT]);
        $this->assertArrayHasKey(RequestOptions::ALLOW_REDIRECTS, $client->requests[0][1]);
    }

    public function testGetUrlDoesNotReplaceHostWhenDisabled()
    {
        config(['app.url' => 'https://example.test']);

        $url = (new OPcache())->getUrl('http://127.0.0.1:8000/health', false);

        $this->assertSame('127.0.0.1', $url->getHost());
        $this->assertSame(8000, $url->getPort());
    }

    public function testGetRemoteScriptsThrowsWhenUrlCannotBeFound()
    {
        $opcache = new class() extends OPcache
        {
            public function getUrl($url = null, $useAppHost = true): ?PUrl
            {
                return null;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Remote interface URL cannot be found!');

        $opcache->getRemoteScripts();
    }

    public function testGetHistoryScriptsReturnsEmptyArrayForInvalidCacheValue()
    {
        config([
            'cache.default' => 'array',
            'cache.stores.array' => ['driver' => 'array'],
        ]);

        $opcache = new OPcache();
        $key = self::callMethod($opcache, 'getCacheKey');
        Cache::store()->set($key, 'invalid-value');

        $history = self::callMethod($opcache, 'getHistoryScripts');

        $this->assertSame([], $history);
    }
}
