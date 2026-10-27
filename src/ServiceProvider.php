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
use HughCube\Laravel\Knight\Console\Commands\LogsRotateCommand;
use HughCube\Laravel\Knight\Console\Commands\MigrateRerun;
use HughCube\Laravel\Knight\Console\Commands\PhpIniFile;
use HughCube\Laravel\Knight\Console\Commands\WalDropSlotCommand;
use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommand;
use HughCube\Laravel\Knight\Console\Commands\WalMonitorSlotsCommand;
use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin as MigrationBlueprintMixin;
use HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin as MigrationPostgresGrammarMixin;
use HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin;
use HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin;
use HughCube\Laravel\Knight\Mixin\Http\RequestMixin;
use HughCube\Laravel\Knight\Mixin\Support\CarbonMixin;
use HughCube\Laravel\Knight\Mixin\Support\CollectionMixin;
use HughCube\Laravel\Knight\OPcache\Commands\ClearCliCacheCommand as OPcacheClearCliCacheCommand;
use HughCube\Laravel\Knight\OPcache\Commands\CompileFilesCommand as OPcacheCompileFilesCommand;
use HughCube\Laravel\Knight\OPcache\Commands\CreatePreloadCommand as OPcacheCreatePreloadCommand;
use HughCube\Laravel\Knight\Scheduling\Commands\DaemonCommand;
use HughCube\Laravel\Knight\Scheduling\Commands\JobPayloadCommand;
use HughCube\Laravel\Knight\Scheduling\Mixin\EventMixin as ScheduleEventMixin;
use HughCube\Laravel\Knight\Support\Runtime;
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

        /** Console Scheduling */
        ScheduleEvent::mixin(new ScheduleEventMixin(), false);
        if (! $this->app->bound(\Illuminate\Console\Scheduling\EventMutex::class)) {
            $this->app->bind(
                \Illuminate\Console\Scheduling\EventMutex::class,
                \Illuminate\Console\Scheduling\CacheEventMutex::class
            );
        }
        if (! $this->app->bound(\Illuminate\Console\Scheduling\SchedulingMutex::class)) {
            $this->app->bind(
                \Illuminate\Console\Scheduling\SchedulingMutex::class,
                \Illuminate\Console\Scheduling\CacheSchedulingMutex::class
            );
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
                LogsRotateCommand::class,
                WalEventDispatchCommand::class,
                WalDropSlotCommand::class,
                WalMonitorSlotsCommand::class,
                DaemonCommand::class,
                JobPayloadCommand::class,
                OPcacheCompileFilesCommand::class,
                OPcacheClearCliCacheCommand::class,
                OPcacheCreatePreloadCommand::class,
            ]);
        }

        $this->configureAuthUserProvider();

        $this->registerModelChangedEvent();
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
