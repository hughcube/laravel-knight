<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/9/3
 * Time: 10:10.
 */

namespace HughCube\Laravel\Knight\Database;

use HughCube\Laravel\Knight\Database\Eloquent\Builder;
use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\Model as KnightModelTrait;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Illuminate\Support\Collection;
use Psr\SimpleCache\CacheInterface;

/**
 * 跨模型批量查询工具类.
 *
 * 将多个模型的缓存查询合并为一次 Redis 操作，减少网络往返次数。
 * 自动从 Model 获取缓存实例，并根据缓存实例分组查询。
 *
 * 使用方式一（静态方法）：
 * <code>
 * $result = MultipleModelBatchFinder::find([
 *     User::class => [1, 2, 3],
 *     Post::class => [1, 2, 3],
 * ]);
 *
 * $users = $result->get(User::class);
 * $posts = $result->get(Post::class);
 * </code>
 *
 * 使用方式二（链式调用）：
 * <code>
 * $result = MultipleModelBatchFinder::make()
 *     ->with(User::class, [1, 2, 3])
 *     ->with(Post::class, [1, 2, 3])
 *     ->get();
 * </code>
 */
class MultipleModelBatchFinder
{
    /**
     * 查询条件 [模型类名 => [条件数组...]].
     *
     * @var array<string, array>
     */
    protected array $queries = [];

    /**
     * Query Builder 缓存 [模型类名 => Builder].
     *
     * @var array<string, Builder>
     */
    protected array $builders = [];

    /**
     * Model 实例缓存 [模型类名 => Model].
     *
     * @var array<string, Model>
     */
    protected array $models = [];

    /**
     * 创建查询实例.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 静态方法：按主键批量查询.
     *
     * @param  array<class-string<Model>, array>  $queries  [模型类名 => [id1, id2, ...], ...]
     * @return MultipleModelBatchResult
     */
    public static function find(array $queries): MultipleModelBatchResult
    {
        $finder = new self();
        foreach ($queries as $class => $ids) {
            $finder->with($class, $ids);
        }

        return $finder->get();
    }

    /**
     * 静态方法：按唯一键批量查询.
     *
     * @param  array<class-string<Model>, array>  $queries  [模型类名 => [[条件1], [条件2], ...], ...]
     * @return MultipleModelBatchResult
     */
    public static function findByUniqueKeys(array $queries): MultipleModelBatchResult
    {
        $finder = new self();
        foreach ($queries as $class => $conditions) {
            $finder->withUniqueKeys($class, $conditions);
        }

        return $finder->get();
    }

    /**
     * 添加按主键查询.
     *
     * @param  class-string<Model>  $class  模型类名
     * @param  array|Collection  $ids  主键 ID 数组
     * @return $this
     */
    public function with(string $class, $ids): self
    {
        $model = $this->getModel($class);
        $keyName = $model->getKeyName();

        foreach ($ids as $id) {
            if ($model->isMatchPk($id)) {
                $this->queries[$class][] = [$keyName => $id];
            }
        }

        return $this;
    }

    /**
     * 添加按唯一键查询.
     *
     * @param  class-string<Model>  $class  模型类名
     * @param  array|Collection  $conditions  条件数组
     * @return $this
     */
    public function withUniqueKeys(string $class, $conditions): self
    {
        foreach ($conditions as $condition) {
            $this->queries[$class][] = $condition;
        }

        return $this;
    }

    /**
     * 执行查询.
     *
     * @return MultipleModelBatchResult
     */
    public function get(): MultipleModelBatchResult
    {
        if (empty($this->queries)) {
            return new MultipleModelBatchResult([]);
        }

        $results = [];

        foreach ($this->groupByCache() as $group) {
            foreach ($this->executeGroup($group['cache'], $group['models']) as $class => $rows) {
                $results[$class] = array_merge($results[$class] ?? [], $rows);
            }
        }

        return new MultipleModelBatchResult($results);
    }

    /**
     * 按缓存实例分组.
     *
     * @return array<array{cache: CacheInterface, models: array}>
     */
    protected function groupByCache(): array
    {
        $groups = [];

        foreach ($this->queries as $class => $conditions) {
            $cache = $this->getQuery($class)->getCache();
            $groupKey = spl_object_id($cache);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = ['cache' => $cache, 'models' => []];
            }

            $groups[$groupKey]['models'][$class] = $conditions;
        }

        return array_values($groups);
    }

    /**
     * 执行单个缓存组的查询.
     *
     * @param  CacheInterface  $cache
     * @param  array  $models  [class => conditions]
     * @return array<string, array>
     */
    protected function executeGroup(CacheInterface $cache, array $models): array
    {
        // 构建缓存键映射
        $keyMap = $this->buildCacheKeyMap($models);
        if (empty($keyMap['keys'])) {
            return $this->queryFromDatabase($models);
        }

        // 批量读取缓存
        $cached = $cache->getMultiple(array_unique($keyMap['keys']));

        // 分离命中和未命中
        $results = [];
        $missed = [];

        foreach ($models as $class => $conditions) {
            $model = $this->getModel($class);
            $results[$class] = [];

            foreach ($conditions as $index => $condition) {
                $cacheKey = $keyMap['map'][$class][$index] ?? null;
                $cachedValue = $cacheKey ? ($cached[$cacheKey] ?? null) : null;

                if ($cachedValue instanceof IlluminateModel) {
                    /** @var Model $cachedValue */
                    $cachedValue->setIsFromCache();
                    $results[$class][] = $cachedValue;
                } elseif (! $model->isCachePlaceholder($cachedValue)) {
                    $missed[$class]['conditions'][$index] = $condition;
                    $missed[$class]['cacheKeys'][$index] = $cacheKey;
                }
            }
        }

        // 查询未命中的数据并写入缓存
        if (! empty($missed)) {
            $this->fetchMissedAndCache($cache, $missed, $results);
        }

        return $results;
    }

    /**
     * 构建缓存键映射.
     *
     * @param  array  $models  [class => conditions]
     * @return array{keys: array, map: array}
     */
    protected function buildCacheKeyMap(array $models): array
    {
        $keys = [];
        $map = [];

        foreach ($models as $class => $conditions) {
            $model = $this->getModel($class);

            foreach ($conditions as $index => $condition) {
                $cacheKey = $model->makeColumnsCacheKey($condition);
                if ($cacheKey) {
                    $keys[] = $cacheKey;
                }
                $map[$class][$index] = $cacheKey;
            }
        }

        return ['keys' => $keys, 'map' => $map];
    }

    /**
     * 查询未命中数据并写入缓存.
     *
     * @param  CacheInterface  $cache
     * @param  array  $missed
     * @param  array  $results
     */
    protected function fetchMissedAndCache(CacheInterface $cache, array $missed, array &$results): void
    {
        $cacheItems = [];
        $ttl = null;

        foreach ($missed as $class => $data) {
            $model = $this->getModel($class);
            $conditions = Collection::make($data['conditions'])->values();

            if ($conditions->isEmpty()) {
                continue;
            }

            // 获取 TTL
            if (null === $ttl) {
                $ttl = $model->getCacheTtl();
            }

            // 查询数据库
            $dbRows = $this->getQuery($class)->queryByUniqueConditions($conditions);

            // 处理结果
            foreach ($data['cacheKeys'] as $cacheKey) {
                if (null === $cacheKey) {
                    continue;
                }

                if ($dbRows->has($cacheKey)) {
                    $row = $dbRows->get($cacheKey);
                    $cacheItems[$cacheKey] = $row;
                    $results[$class][] = $row;
                } elseif ($model->hasCachePlaceholder()) {
                    $cacheItems[$cacheKey] = $model->getCachePlaceholder();
                }
            }
        }

        // 批量写入缓存
        if (! empty($cacheItems)) {
            $cache->setMultiple($cacheItems, $ttl ?? 3600);
        }
    }

    /**
     * 从数据库批量查询.
     *
     * @param  array<string, array>  $models  [class => conditions]
     * @return array<string, array>
     */
    protected function queryFromDatabase(array $models): array
    {
        $results = [];

        foreach ($models as $class => $conditions) {
            $conditions = Collection::make($conditions);

            $results[$class] = $conditions->isEmpty() ? [] : $this->getQuery($class)->queryByUniqueConditions($conditions)->values()->all();
        }

        return $results;
    }

    /**
     * 获取模型实例.
     *
     * @param  class-string<Model>  $class  模型类名
     * @return Model
     */
    protected function getModel(string $class)
    {
        if (! isset($this->models[$class])) {
            $model = $this->getQuery($class)->getModel();

            if (! in_array(KnightModelTrait::class, class_uses_recursive($model), true)) {
                throw new \InvalidArgumentException(sprintf('%s must use trait %s', get_class($model), KnightModelTrait::class));
            }

            $this->models[$class] = $model;
        }

        return $this->models[$class];
    }

    /**
     * 获取 Query Builder（带缓存）.
     *
     * @param  class-string<Model>  $class  模型类名
     * @return Builder
     */
    protected function getQuery(string $class)
    {
        if (! isset($this->builders[$class])) {
            $this->builders[$class] = $class::query();
        }

        return $this->builders[$class];
    }
}
