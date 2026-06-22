<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/10
 * Time: 4:29 下午.
 */

namespace HughCube\Laravel\Knight;

use Carbon\Carbon;
use HughCube\Laravel\Knight\Auth\ModelUserProvider;
use HughCube\Laravel\Knight\Console\Commands\ClearModelCache;
use HughCube\Laravel\Knight\Console\Commands\Config;
use HughCube\Laravel\Knight\Console\Commands\DatabaseCheckSequencesCommand;
use HughCube\Laravel\Knight\Console\Commands\DatabaseRandomizeSequences;
use HughCube\Laravel\Knight\Console\Commands\DatabaseResetAutoIncrementStartId;
use HughCube\Laravel\Knight\Console\Commands\Environment;
use HughCube\Laravel\Knight\Console\Commands\GenerateMixinIdeHelperCommand;
use HughCube\Laravel\Knight\Console\Commands\KRTest;
use HughCube\Laravel\Knight\Console\Commands\LogsPruneCommand;
use HughCube\Laravel\Knight\Console\Commands\MigrateRerun;
use HughCube\Laravel\Knight\Console\Commands\PhpIniFile;
use HughCube\Laravel\Knight\Console\Commands\WalDropSlotCommand;
use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommand;
use HughCube\Laravel\Knight\Console\Commands\WalMonitorSlotsCommand;
use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin as MigrationBlueprintMixin;
use HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin as MigrationPostgresGrammarMixin;
use HughCube\Laravel\Knight\Http\Actions\DevopsSystemAction;
use HughCube\Laravel\Knight\Http\Actions\PhpInfoAction;
use HughCube\Laravel\Knight\Http\Actions\PingAction;
use HughCube\Laravel\Knight\Http\Actions\RequestLogAction;
use HughCube\Laravel\Knight\Http\Actions\RequestShowAction;
use HughCube\Laravel\Knight\Mixin\Console\Scheduling\EventMixin as ScheduleEventMixin;
use HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin;
use HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin;
use HughCube\Laravel\Knight\Mixin\Http\RequestMixin;
use HughCube\Laravel\Knight\Mixin\Support\CarbonMixin;
use HughCube\Laravel\Knight\Mixin\Support\CollectionMixin;
use HughCube\Laravel\Knight\OPcache\Actions\ResetAction as OPcacheResetAction;
use HughCube\Laravel\Knight\OPcache\Actions\ScriptsAction as OPcacheScriptsAction;
use HughCube\Laravel\Knight\OPcache\Actions\StatesAction as OPcacheStatesAction;
use HughCube\Laravel\Knight\OPcache\Commands\ClearCliCacheCommand as OPcacheClearCliCacheCommand;
use HughCube\Laravel\Knight\OPcache\Commands\CompileFilesCommand as OPcacheCompileFilesCommand;
use HughCube\Laravel\Knight\OPcache\Commands\CreatePreloadCommand as OPcacheCreatePreloadCommand;
use HughCube\Laravel\Knight\Support\Runtime;
use Illuminate\Config\Repository;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as SchemaPostgresGrammar;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use ReflectionException;

/**
 * @property LaravelApplication|LumenApplication $app
 */
class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register the provider.
     *
     * @throws ReflectionException
     */
    public function register()
    {
        Collection::mixin(new CollectionMixin(), false);

        /** Database */
        Grammar::mixin(new GrammarMixin(), false);
        Builder::mixin(new BuilderMixin(), false);

        /** Http */
        Request::mixin(new RequestMixin(), false);

        /** Carbon */
        Carbon::mixin(new CarbonMixin());

        /** Console Scheduling - 仅在 CLI 模式下注册 */
        if ($this->app->runningInConsole()) {
            ScheduleEvent::mixin(new ScheduleEventMixin(), false);
        }

        /** Migration Schema - 仅在 ide-helper 命令时注册 */
        if (Runtime::isRunningCommand('ide-helper:*')) {
            Blueprint::mixin(new MigrationBlueprintMixin(), false);
            SchemaPostgresGrammar::mixin(new MigrationPostgresGrammarMixin(), false);
        }
    }

    /**
     * Boot the provider.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Config::class,
                DatabaseCheckSequencesCommand::class,
                DatabaseRandomizeSequences::class,
                DatabaseResetAutoIncrementStartId::class,
                Environment::class,
                PhpIniFile::class,
                KRTest::class,
                ClearModelCache::class,
                GenerateMixinIdeHelperCommand::class,
                MigrateRerun::class,
                LogsPruneCommand::class,
                WalEventDispatchCommand::class,
                WalDropSlotCommand::class,
                WalMonitorSlotsCommand::class,
            ]);
        }

        $this->bootDevops();
        $this->bootOpCache();
        $this->bootRequest();
        $this->bootPhpInfo();
        $this->bootHealthCheck();

        $this->configureAuthUserProvider();

        $this->registerModelChangedEvent();
    }

    protected function bootOpCache()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OPcacheCompileFilesCommand::class,
                OPcacheClearCliCacheCommand::class,
                OPcacheCreatePreloadCommand::class,
            ]);
        }

        /** @var Repository $config */
        $config = $this->app->make('config');
        if (!$this->app->routesAreCached() && false !== ($prefix = $config->get('knight.opcache.route_prefix', false))) {
            Route::group(['prefix' => $prefix], function () use ($config) {
                if (false !== ($action = $config->get('knight.opcache.action.scripts'))) {
                    Route::any('/opcache/scripts', $action ?: OPcacheScriptsAction::class)->name('knight.opcache.scripts');
                }

                if (false !== ($action = $config->get('knight.opcache.action.states'))) {
                    Route::any('/opcache/states', $action ?: OPcacheStatesAction::class)->name('knight.opcache.states');
                }

                if (false !== ($action = $config->get('knight.opcache.action.reset'))) {
                    Route::any('/opcache/reset', $action ?: OPcacheResetAction::class)->name('knight.opcache.reset');
                }
            });
        }
    }

    protected function bootRequest()
    {
        /** @var Repository $config */
        $config = $this->app->make('config');
        if (!$this->app->routesAreCached() && false !== ($prefix = $config->get('knight.request.route_prefix', false))) {
            Route::group(['prefix' => $prefix], function () use ($config) {
                if (false !== ($action = $config->get('knight.request.action.log'))) {
                    Route::any('/request/log', $action ?: RequestLogAction::class)->name('knight.request.log');
                }

                if (false !== ($action = $config->get('knight.request.action.show'))) {
                    Route::any('/request/show', $action ?: RequestShowAction::class)->name('knight.request.show');
                }
            });
        }
    }

    protected function bootHealthCheck()
    {
        /** @var Repository $config */
        $config = $this->app->make('config');
        if (!$this->app->routesAreCached() && false !== ($prefix = $config->get('knight.healthcheck.route_prefix'))) {
            Route::group(['prefix' => $prefix], function () use ($config) {
                if (false !== ($action = $config->get('knight.healthcheck.action.healthcheck'))) {
                    Route::any('/healthcheck', $action ?: PingAction::class)->name('knight.healthcheck');
                }
            });
        }
    }

    protected function bootPhpInfo()
    {
        /** @var Repository $config */
        $config = $this->app->make('config');
        if (!$this->app->routesAreCached() && false !== ($prefix = $config->get('knight.phpinfo.route_prefix', false))) {
            Route::group(['prefix' => $prefix], function () use ($config) {
                if (false !== ($action = $config->get('knight.phpinfo.action.phpinfo'))) {
                    Route::any('/phpinfo', $action ?: PhpInfoAction::class)->name('knight.phpinfo');
                }
            });
        }
    }

    protected function bootDevops()
    {
        /** @var Repository $config */
        $config = $this->app->make('config');
        if (!$this->app->routesAreCached() && false !== ($prefix = $config->get('knight.devops.route_prefix', false))) {
            Route::group(['prefix' => $prefix], function () use ($config) {
                if (false !== ($action = $config->get('knight.devops.action.system'))) {
                    Route::any('/devops/system', $action ?: DevopsSystemAction::class)->name('knight.devops.system');
                }
            });
        }
    }

    /**
     * @return void
     *
     * @see \Illuminate\Database\Eloquent\Concerns\HasEvents::getObservableEvents
     */
    protected function registerModelChangedEvent()
    {
        /** @var Dispatcher|null $dispatcher */
        $dispatcher = EloquentModel::getEventDispatcher();
        if (null === $dispatcher) {
            return;
        }

        $events = [
            'eloquent.created: *',
            'eloquent.deleted: *',
            'eloquent.updated: *',
            'eloquent.restored: *',
        ];
        $dispatcher->listen($events, function ($event, $models) {
            /** @var Model $model */
            foreach ($models as $model) {
                if (method_exists($model, 'onKnightModelChanged')) {
                    $model->onKnightModelChanged();
                } elseif (method_exists($model, 'deleteRowCache')) {
                    $model->deleteRowCache();
                }
            }
        });
    }

    protected function configureAuthUserProvider()
    {
        Auth::resolved(function ($auth) {
            $auth->provider('knightModel', function ($app, array $config) {
                return new ModelUserProvider($config['model']);
            });
        });
    }
}
