<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/4
 * Time: 12:00 下午.
 */

namespace HughCube\Laravel\Knight\Database\Eloquent\Traits;

use HughCube\Laravel\Knight\Exceptions\OptimisticLockException;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Trait OptimisticLocking.
 *
 * @mixin EloquentModel
 */
trait OptimisticLock
{
    /**
     * 是否启用乐观锁检查.
     */
    protected bool $optimisticLockEnabled = false;

    protected static function bootOptimisticLock(): void
    {
        // 创建时自动设置默认版本号
        static::creating(function ($model) {
            /** @var static $model */
            $column = $model->lockDataVersionColumn();
            if (null === $model->{$column}) {
                $model->{$column} = $model->defaultModelDataVersion();
            }
        });

        // 更新时自动递增版本号
        static::updating(function ($model) {
            /** @var static $model */
            if ($model->isAutoIncrementDataVersion()) {
                $column = $model->lockDataVersionColumn();
                $model->{$column} = $model->getOriginal($column) + 1;
            }
        });
    }

    /**
     * 更新后检查乐观锁（由 Builder::update 调用）.
     *
     * 只在单个模型的 save() 操作时检查，不影响批量更新
     *
     * @param  int  $affectedRows  影响的行数
     *
     * @throws OptimisticLockException
     */
    public function checkOptimisticLockAfterUpdate(int $affectedRows): void
    {
        // exists 为 true 表示是单个模型的 save 操作，批量更新时 exists 为 false
        if ($this->exists && $this->isOptimisticLockEnabled() && $affectedRows === 0) {
            throw new OptimisticLockException(
                sprintf('Optimistic lock failed for model [%s] with key [%s]. No rows affected.', static::class, $this->getKey())
            );
        }
    }

    /**
     * 为 setKeysForSaveQuery 添加乐观锁版本条件.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQueryFromOptimisticLock($query)
    {
        // 更新操作且启用乐观锁时，添加版本条件
        if ($this->exists && $this->isOptimisticLockEnabled()) {
            $column = $this->lockDataVersionColumn();
            $query->where($column, $this->getOriginal($column));
        }

        return $query;
    }

    /**
     * 获取版本号字段名.
     */
    public static function lockDataVersionColumn(): string
    {
        return defined(static::class.'::DATA_VERSION') ? static::DATA_VERSION : 'data_version';
    }

    /**
     * 获取默认版本号.
     */
    public static function defaultModelDataVersion(): int
    {
        return 1;
    }

    /**
     * 是否自动递增版本号.
     */
    public function isAutoIncrementDataVersion(): bool
    {
        return ! defined(static::class.'::AUTO_INCREMENT_DATA_VERSION') || static::AUTO_INCREMENT_DATA_VERSION;
    }

    /**
     * 是否启用乐观锁检查.
     */
    public function isOptimisticLockEnabled(): bool
    {
        return $this->optimisticLockEnabled;
    }

    /**
     * 禁用乐观锁检查.
     */
    public function disableOptimisticLock()
    {
        $this->optimisticLockEnabled = false;

        return $this;
    }

    /**
     * 启用乐观锁检查.
     */
    public function enableOptimisticLock()
    {
        $this->optimisticLockEnabled = true;

        return $this;
    }

    /**
     * 启用乐观锁检查并保存模型.
     *
     * @param  array  $options
     * @return bool
     *
     * @throws OptimisticLockException
     */
    public function useOptimisticLockSave(array $options = [])
    {
        $previous = $this->optimisticLockEnabled;
        $this->optimisticLockEnabled = true;

        try {
            return $this->save($options);
        } finally {
            $this->optimisticLockEnabled = $previous;
        }
    }

    /**
     * 启用乐观锁检查并更新模型.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     *
     * @throws OptimisticLockException
     */
    public function useOptimisticLockUpdate(array $attributes = [], array $options = [])
    {
        $previous = $this->optimisticLockEnabled;
        $this->optimisticLockEnabled = true;

        try {
            return $this->update($attributes, $options);
        } finally {
            $this->optimisticLockEnabled = $previous;
        }
    }
}
