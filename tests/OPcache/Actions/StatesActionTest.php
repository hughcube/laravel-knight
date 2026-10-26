<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/15
 * Time: 8:42 下午.
 */

namespace HughCube\Laravel\Knight\OPcache\Actions;

require_once __DIR__.'/../Support/OpcacheOverrides.php';

use HughCube\Laravel\Knight\Tests\OPcache\OpcacheTestOverrides;

if (! function_exists(__NAMESPACE__.'\extension_loaded')) {
    function extension_loaded($name): bool
    {
        return OpcacheTestOverrides::$extensionLoaded;
    }
}

if (! function_exists(__NAMESPACE__.'\opcache_get_status')) {
    function opcache_get_status()
    {
        return OpcacheTestOverrides::$opcacheStatusEnabled ? ['enabled' => true] : false;
    }
}

namespace HughCube\Laravel\Knight\Tests\OPcache\Actions;

use HughCube\Laravel\Knight\Exceptions\UserException;
use HughCube\Laravel\Knight\OPcache\Actions\StatesAction;
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

    public function testRulesContainAsJson()
    {
        $action = new StatesAction();

        $rules = $this->callMethod($action, 'rules');

        $this->assertArrayHasKey('as_json', $rules);
        $this->assertContains('boolean', $rules['as_json']);
    }

    public function testIsAsJsonReadsParameters()
    {
        $request = Request::create('/opcache/states', 'GET', ['as_json' => 1]);
        $this->app->instance('request', $request);

        $action = $this->app->make(StatesAction::class);
        $this->assertTrue($this->callMethod($action, 'isAsJson'));

        $request = Request::create('/opcache/states', 'GET', ['as_json' => 0]);
        $this->app->instance('request', $request);

        $action = $this->app->make(StatesAction::class);
        $this->assertFalse($this->callMethod($action, 'isAsJson'));
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

    public function testActionReturnsJsonWhenRequested()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = $this->app->make(StatesAction::class);
        $request = Request::create('/opcache/states', 'GET', ['as_json' => 1]);
        $this->app->instance('request', $request);

        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Success', $payload['Code']);
        $this->assertTrue($payload['Data']['enabled']);
    }

    public function testActionReturnsHtmlWhenNotJson()
    {
        OpcacheTestOverrides::$extensionLoaded = true;
        OpcacheTestOverrides::$opcacheStatusEnabled = true;

        $action = new class() extends StatesAction
        {
            protected function renderView($file)
            {
                return 'ok';
            }
        };

        $request = Request::create('/opcache/states', 'GET', ['as_json' => 0]);
        $this->app->instance('request', $request);

        $response = $this->callMethod($action, 'action');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->getContent());
    }
}
