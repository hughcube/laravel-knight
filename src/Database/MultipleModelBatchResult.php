<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/9/3
 * Time: 10:10.
 */

namespace HughCube\Laravel\Knight\Database;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * 批量查询结果容器.
 *
 * 用于存储 MultipleModelBatchFinder 的查询结果，提供按模型类名获取结果的方法。
 *
 * @implements IteratorAggregate<string, EloquentCollection>
 */
class MultipleModelBatchResult implements Countable, IteratorAggregate, Arrayable
{
    /**
     * @var array<class-string, array> [模型类名 => [模型实例...], ...]
     */
    protected array $results;

    /**
     * @param  array<class-string, array>  $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * 检查是否有任何结果（所有模型）.
     *
     * @return bool
     */
    public function has()
    {
        return $this->count() > 0;
    }

    /**
     * 检查是否包含指定模型的结果.
     *
     * @param  class-string  $class  模型类名
     * @return bool
     */
    public function hasOf(string $class)
    {
        return $this->countOf($class) > 0;
    }

    /**
     * 获取所有模型合并到一个列表中.
     *
     * @return Collection
     */
    public function get()
    {
        $items = [];

        foreach ($this->results as $models) {
            foreach ($models as $model) {
                $items[] = $model;
            }
        }

        return Collection::make($items);
    }

    /**
     * 获取指定模型的查询结果.
     *
     * @param  class-string  $class  模型类名
     * @return EloquentCollection
     */
    public function getOf(string $class)
    {
        $models = $this->results[$class] ?? [];

        if (class_exists($class) && method_exists($class, 'newCollection')) {
            /** @var IlluminateModel $instance */
            $instance = new $class();

            /** @var EloquentCollection $collection */
            $collection = $instance->newCollection($models);

            return $collection;
        }

        return new EloquentCollection($models);
    }

    /**
     * 获取所有记录的总数.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->results as $models) {
            $count += count($models);
        }

        return $count;
    }

    /**
     * 获取指定模型的记录数.
     *
     * @param  class-string  $class  模型类名
     * @return int
     */
    public function countOf(string $class)
    {
        return count($this->results[$class] ?? []);
    }

    /**
     * 获取所有查询的模型类名.
     *
     * @return array<class-string>
     */
    public function getModelClasses()
    {
        return array_keys($this->results);
    }

    /**
     * 遍历每一个模型实例.
     *
     * @param  callable(IlluminateModel, int): void  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        $index = 0;
        foreach ($this->results as $models) {
            foreach ($models as $model) {
                $callback($model, $index);
                $index++;
            }
        }

        return $this;
    }

    /**
     * 遍历指定模型类的每一个模型实例.
     *
     * @param  class-string  $class  模型类名
     * @param  callable(IlluminateModel, int): void  $callback
     * @return $this
     */
    public function eachOf(string $class, callable $callback)
    {
        foreach (($this->results[$class] ?? []) as $index => $model) {
            $callback($model, $index);
        }

        return $this;
    }

    /**
     * 转换为数组（扁平列表）.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->get()->toArray();
    }

    /**
     * 实现 IteratorAggregate 接口，支持 foreach 遍历.
     *
     * 遍历每一个模型实例.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->get()->all());
    }
}
