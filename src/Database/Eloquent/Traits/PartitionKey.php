<?php

namespace HughCube\Laravel\Knight\Database\Eloquent\Traits;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * 分区表支持 Trait.
 *
 * 为分区表模型自动在 SQL WHERE 子句中加入分区键，避免全分区扫描:
 * - UPDATE/DELETE: 通过 setKeysForSaveQueryFromPartitionKey 自动注入
 * - SELECT (refresh/fresh): 通过 setKeysForSelectQueryFromPartitionKey 自动注入
 * - 缓存刷新: 通过 onChangeRefreshCacheKeys 自动包含分区键
 *
 * 使用方式：
 * - use PartitionKey;
 * - 覆写 partitionKeyColumns() 返回分区键字段列表
 *
 * @mixin EloquentModel
 */
trait PartitionKey
{
    /**
     * 获取分区键字段列表.
     *
     * @return string[]
     */
    public function partitionKeyColumns(): array
    {
        return [];
    }

    /**
     * 为 setKeysForSaveQuery 添加分区键条件 (UPDATE/DELETE).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQueryFromPartitionKey($query)
    {
        foreach ($this->partitionKeyColumns() as $column) {
            $query->where($column, $this->getOriginal($column));
        }

        return $query;
    }

    /**
     * 为 setKeysForSelectQuery 添加分区键条件 (refresh/fresh).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSelectQueryFromPartitionKey($query)
    {
        foreach ($this->partitionKeyColumns() as $column) {
            $value = $this->getOriginal($column) ?? $this->getAttribute($column);
            if (null !== $value) {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /**
     * 缓存刷新键：自动包含分区键 + 主键的组合，以及纯主键兜底.
     *
     * 返回两组缓存键:
     * 1. [partitionKey => X, pk => Y] — 匹配带分区键的缓存查询
     * 2. [pk => Y] — 兜底，匹配纯主键的缓存查询
     *
     * @return array
     */
    public function onChangeRefreshCacheKeys(): array
    {
        $columns = $this->partitionKeyColumns();

        if (empty($columns)) {
            return [
                [$this->getKeyName() => $this->getKey()],
            ];
        }

        $partitionCondition = [];
        foreach ($columns as $column) {
            $partitionCondition[$column] = $this->getAttribute($column);
        }
        $partitionCondition[$this->getKeyName()] = $this->getKey();

        return [
            $partitionCondition,
            [$this->getKeyName() => $this->getKey()],
        ];
    }
}
