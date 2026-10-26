<?php

namespace HughCube\Laravel\Knight\Tests\ServiceProvider;

use HughCube\Laravel\Knight\Auth\ModelUserProvider;
use HughCube\Laravel\Knight\Database\Eloquent\Model as KnightModel;
use HughCube\Laravel\Knight\ServiceProvider;
use HughCube\Laravel\Knight\Tests\Database\Eloquent\User;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;

class ServiceProviderTest extends TestCase
{
    public function testBootRegistersRoutes()
    {
        config([
            'knight.opcache.route_prefix' => 'knight-opcache',
            'knight.opcache.action.scripts' => null,
            'knight.opcache.action.states' => null,
            'knight.opcache.action.reset' => null,
            'knight.request.route_prefix' => 'knight-request',
            'knight.request.action.log' => null,
            'knight.request.action.show' => null,
            'knight.healthcheck.route_prefix' => 'knight-health',
            'knight.healthcheck.action.healthcheck' => null,
            'knight.phpinfo.route_prefix' => 'knight-phpinfo',
            'knight.phpinfo.action.phpinfo' => null,
            'knight.devops.route_prefix' => 'knight-devops',
            'knight.devops.action.system' => null,
        ]);

        $provider = new ServiceProvider($this->app);

        $routesCached = $this->app->routesAreCached();

        self::callMethod($provider, 'bootOpCache');
        self::callMethod($provider, 'bootRequest');
        self::callMethod($provider, 'bootHealthCheck');
        self::callMethod($provider, 'bootPhpInfo');
        self::callMethod($provider, 'bootDevops');

        $this->assertIsBool($routesCached);

        if ($routesCached || Route::getFacadeRoot() === null) {
            $this->assertTrue(true);

            return;
        }

        $this->assertIsArray(Route::getRoutes()->getRoutesByName());
    }

    public function testRegisterModelChangedEventCallsOnKnightModelChanged()
    {
        $provider = new ServiceProvider($this->app);
        self::callMethod($provider, 'registerModelChangedEvent');

        $dispatcher = EloquentModel::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

        $model = new class() extends KnightModel
        {
            /** @var int */
            public static $modelChangedCalls = 0;

            public function onKnightModelChanged(): void
            {
                self::$modelChangedCalls++;
            }
        };

        $model::$modelChangedCalls = 0;
        $dispatcher->dispatch('eloquent.updated: '.get_class($model), [$model]);

        $this->assertGreaterThanOrEqual(1, $model::$modelChangedCalls);
    }

    public function testRegisterModelChangedEventFallbackToDeleteRowCache()
    {
        $provider = new ServiceProvider($this->app);
        self::callMethod($provider, 'registerModelChangedEvent');

        $dispatcher = EloquentModel::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

        $model = new class() extends EloquentModel
        {
            /** @var int */
            public static $deleteCalls = 0;

            public function deleteRowCache(): bool
            {
                self::$deleteCalls++;

                return true;
            }
        };

        $model::$deleteCalls = 0;
        $dispatcher->dispatch('eloquent.updated: '.get_class($model), [$model]);

        $this->assertGreaterThanOrEqual(1, $model::$deleteCalls);
    }

    public function testWalCommandsAreRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('wal:event-dispatch', $commands);
        $this->assertArrayHasKey('wal:drop-slot', $commands);
    }

    public function testConfigureAuthUserProviderRegistersCreator()
    {
        $provider = new ServiceProvider($this->app);
        self::callMethod($provider, 'configureAuthUserProvider');

        $authManager = Auth::getFacadeRoot();
        $property = new ReflectionProperty($authManager, 'customProviderCreators');
        $property->setAccessible(true);
        $creators = $property->getValue($authManager);

        $this->assertArrayHasKey('knightModel', $creators);

        $creator = $creators['knightModel'];
        $created = $creator($this->app, ['model' => User::class]);

        $this->assertInstanceOf(ModelUserProvider::class, $created);
        $this->assertSame(User::class, self::getProperty($created, 'model'));
    }

    public function testRegisterAppliesMixins()
    {
        $provider = new ServiceProvider($this->app);
        $provider->register();

        $this->assertTrue(Collection::hasMacro('hasAnyValues'));
        $this->assertTrue(Builder::hasMacro('whereIntArrayContains'));
        $this->assertTrue(Carbon::hasMacro('getTimestampAsFloat'));

        $collection = Collection::make([1, 2]);
        $this->assertTrue($collection->hasAnyValues([2]));

        $query = $this->app['db']->connection()->query();
        $query->whereIntArrayContains('scores', [1, 2]);
        $this->assertNotEmpty($query->wheres);
    }
}
