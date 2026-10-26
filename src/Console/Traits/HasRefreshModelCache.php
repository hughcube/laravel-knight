<?php

namespace HughCube\Laravel\Knight\Console\Traits;

use Exception;
use HughCube\Laravel\Knight\Database\Eloquent\Builder;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\Model as KnightModelTrait;
use Illuminate\Database\Eloquent\Model as EloquentModel;

trait HasRefreshModelCache
{
    /**
     * @param  Builder  $query
     * @param  bool  $force  是否强制刷新, false 时仅在缓存与数据库不一致时刷新
     *
     * @throws Exception
     */
    protected function eachRefreshModelCache(Builder $query, bool $force = true): void
    {
        $count = $query->clone()->count();

        if ($count <= 0) {
            $this->info(sprintf('刷新 %s 缓存, 未查询到数据', get_class($query->getModel())));

            return;
        }

        $doneCount = 0;

        $query->clone()->eachById(function (EloquentModel $model) use (&$doneCount, $count, $force) {
            /** @var KnightModelTrait $model */
            $modelClass = get_class($model);

            $cacheModel = $modelClass::findById($model->getKey());
            $wasChanged = $force || ! $model->hasSameAttributes($cacheModel);

            if ($wasChanged) {
                $model->deleteRowCache();
                $model->resetRowCache();

                $cacheModel = $modelClass::findById($model->getKey());
                if (! $model->hasSameAttributes($cacheModel)) {
                    throw new Exception(sprintf('重置 %s(%s) 后对比缓存失败!', $modelClass, $model->getKey()));
                }
            }

            $doneCount++;
            $this->info(sprintf('%s/%s 刷新 %s(%s) 缓存, %s', $doneCount, $count, $modelClass, $model->getKey(), $wasChanged ? '成功' : '跳过'));
        });
    }
}
