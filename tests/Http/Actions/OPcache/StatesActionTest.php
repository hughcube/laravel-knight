<?php

namespace HughCube\Laravel\Knight\Http\Actions\OPcache;

require_once __DIR__.'/../../../OPcache/Support/OpcacheOverrides.php';

use HughCube\Laravel\Knight\Tests\OPcache\OpcacheTestOverrides;

if (!function_exists(__NAMESPACE__.'\extension_loaded')) {
    function extension_loaded($name): bool
    {
        return OpcacheTestOverrides::$extensionLoaded;
    }
}

if (!function_exists(__NAMESPACE__.'\opcache_get_status')) {
    function opcache_get_status()
    {
        return OpcacheTestOverrides::$opcacheStatusEnabled ? [
            'opcache_enabled' => true,
            'cache_full' => false,
            'restart_pending' => false,
            'restart_in_progress' => false,
            'memory_usage' => ['used_memory' => 100],
            'interned_strings_usage' => [],
            'opcache_statistics' => [],
            'scripts' => [
                '/app/foo.php' => ['hits' => 10],
                '/app/bar.php' => ['hits' => 5],
            ],
        ] : false;
    }
}

namespace HughCube\Laravel\Knight\Tests\Http\Actions\OPcache;

use HughCube\Laravel\Knight\Exceptions\UserException;
use HughCube\Laravel\Knight\Http\Actions\OPcache\StatesAction;
use HughCube\Laravel\Knight\Tests\OPcache\OpcacheTestOverrides;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatesActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OpcacheTestOverrides::resetDefaults();
    }

    public function testRulesContainParameters()
    {
        $action = new StatesAction();

        $rules = $this->callMethod($action, 'rules');

        $this->assertArrayHasKey('x-format', $rules);
        $this->assertArrayHasKey('x-as-json', $rules);
        $this->assertArrayHasKey('x-scripts', $rules);
    }

    public function testIsJsonReadsParameters()
    {
        $request = Request::create('/opcache/states', 'GET', ['x-format' => 'json']);
        $this->app->instance('request', $request);
        $action = $this->app->make(StatesAction::class);
        $this->assertTrue($this->callMethod($action, 'isJson'));

        $request = Request::create('/opcache/states', 'GET', ['x-as-json' => 1]);
        $this->app->instance('request', $request);
        $action = $this->app->make(StatesAction::class);
        $this->assertTrue($this->callMethod($action, 'isJson'));

        $request = Request::create('/opcache/states', 'GET');
        $this->app->instance('request', $request);
        $action = $this->app->make(StatesAction::class);
        $this->assertFalse($this->callMethod($action, 'isJson'));
    }

    public function testRenderViewReturnsContent()
    {
        $action = new StatesAction();
        $file = tempnam(sys_get_temp_dir(), 'opcache-view');

        file_put_contents($file, '<?php echo "ok";');

        try {
            $output = $this->callMethod($action, 'renderView', [$file]);
            $this->assertSame('ok', $output);
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testActionThrowsWhenExtensionMissing()
    {
        OpcacheTestOverrides::$extensionLoaded = false;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = $this->app->make(StatesAction::class);
        $request = Request::create('/opcache/states', 'GET');
        $this->app->instance('request', $request);

        $this->expectException(UserException::class);
        $this->callMethod($action, 'action');
    }

    public function testActionThrowsWhenOpcacheDisabled()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = false;

        $action = $this->app->make(StatesAction::class);
        $request = Request::create('/opcache/states', 'GET');
        $this->app->instance('request', $request);

        $this->expectException(UserException::class);
        $this->callMethod($action, 'action');
    }

    public function testActionReturnsJsonWithoutScripts()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = $this->app->make(StatesAction::class);
        $request = Request::create('/opcache/states', 'GET', ['x-format' => 'json']);
        $this->app->instance('request', $request);

        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Success', $payload['Code']);
        $this->assertTrue($payload['Data']['opcache_enabled']);
        $this->assertSame(2, $payload['Data']['scripts_count']);
        $this->assertNull($payload['Data']['scripts']);
    }

    public function testActionReturnsJsonWithScripts()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = $this->app->make(StatesAction::class);
        $request = Request::create('/opcache/states', 'GET', ['x-format' => 'json', 'x-scripts' => 1]);
        $this->app->instance('request', $request);

        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Success', $payload['Code']);
        $this->assertTrue($payload['Data']['opcache_enabled']);
        $this->assertSame(2, $payload['Data']['scripts_count']);
        $this->assertEquals(['/app/foo.php', '/app/bar.php'], $payload['Data']['scripts']);
    }

    public function testActionReturnsHtmlWhenNotJson()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = new class() extends StatesAction {
            protected function renderView(string $file): string
            {
                return 'ok';
            }
        };

        $request = Request::create('/opcache/states', 'GET');
        $this->app->instance('request', $request);

        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->getContent());
    }
}
