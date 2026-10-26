<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2025/5/12
 * Time: 23:04.
 */

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Database\Eloquent\Model as KnightModel;
use HughCube\Laravel\Knight\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

class ClearModelCache extends \HughCube\Laravel\Knight\Console\Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'cache:clear-model';

    /**
     * @inheritdoc
     */
    protected $description = 'clear model cache!';

    /**
     * Execute the console command.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    public function handle(Schedule $schedule): void
    {
        $files = Finder::create()->files()
            ->in(app_path('Models'))
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->depth(0)
            ->sortByName();

        $models = [];
        foreach ($files as $file) {
            $class = Str::replaceFirst(base_path(), '', $file->getRealPath());
            $class = trim($class, DIRECTORY_SEPARATOR);
            $class = rtrim($class, '.php');
            $class = strtr($class, ['/' => '\\']);
            $class = Str::ucfirst($class);

            try {
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                continue;
            }

            if (! $reflection->isInstantiable() || ! $reflection->hasMethod('deleteRowCache')) {
                continue;
            }

            $models[] = $class;
        }

        /** @var KnightModel $model */
        $model = $this->choice('Please select the model to clear: ', $models);

        $scopes = $this->ask(sprintf(
            'Please enter the primary key(s) for model (%s) to clear, separated by commas; enter "*" to clear cache for all records!',
            $model
        ));
        $this->info(sprintf('scopes: %s', $scopes));

        $query = $model::noCacheQuery();
        if ('*' !== $scopes) {
            $ids = Collection::wrap(explode(',', $scopes))->filter()->unique()->values();
            $query->whereIn('id', $ids);
        }

        $doneCount = 0;
        $count = $query->clone()->count();
        $query->eachById(function ($row) use (&$doneCount, $count) {
            /** @var KnightModel $row */
            $row->deleteRowCache();

            $this->info(sprintf(
                '%s/%s: Model %s (ID: %s) cache deleted successfully.',
                ++$doneCount,
                $count,
                get_class($row),
                $row->getKey()
            ));
        });
    }
}
