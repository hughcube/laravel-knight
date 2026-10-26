<?php

/* @noinspection ALL */
// @formatter:off
// phpcs:ignoreFile

/**
 * IDE helper for KnightEloquentBuilder methods.
 *
 * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder
 */

namespace Illuminate\Database\Eloquent;

/**
 * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder
 */
class Builder
{
    /**
     * 禁用缓存查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::noCache()
     *
     * @return $this
     */
    public function noCache()
    {
    }

    /**
     * 获取缓存实例.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::getCache()
     *
     * @return \Psr\SimpleCache\CacheInterface
     */
    public function getCache()
    {
    }

    /**
     * 根据主键查找单个模型.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::findByPk()
     *
     * @param  mixed  $pk  主键值
     * @return \Illuminate\Database\Eloquent\Model|\HughCube\Laravel\Knight\Database\Eloquent\Model|mixed|null
     */
    public function findByPk($pk)
    {
    }

    /**
     * 根据多个主键查找模型集合.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::findByPks()
     *
     * @param  array|\Illuminate\Contracts\Support\Arrayable|\Traversable  $pks  主键值集合
     * @return \HughCube\Laravel\Knight\Database\Eloquent\Collection
     */
    public function findByPks($pks)
    {
    }

    /**
     * 根据单个唯一列的多个值查找模型集合.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::findByOneUniqueColumnValues()
     *
     * @param  string  $column  唯一列名
     * @param  mixed  $values  值集合
     * @return \HughCube\Laravel\Knight\Database\Eloquent\Collection
     */
    public function findByOneUniqueColumnValues($column, $values)
    {
    }

    /**
     * 根据唯一键条件查找单个模型.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::findUniqueRow()
     *
     * @param  mixed  $id  唯一键条件 (如 ['id' => 1])
     * @return \Illuminate\Database\Eloquent\Model|\HughCube\Laravel\Knight\Database\Eloquent\Model|mixed|null
     */
    public function findUniqueRow($id)
    {
    }

    /**
     * 根据唯一键条件查找多个模型 (带缓存).
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::findUniqueRows()
     *
     * @param  array|\Illuminate\Contracts\Support\Arrayable|\Traversable  $ids  唯一键条件集合, 格式: [['id' => 1, 'id2' => 1], ...]
     * @return \HughCube\Laravel\Knight\Database\Eloquent\Collection
     */
    public function findUniqueRows($ids)
    {
    }

    /**
     * 根据唯一键条件直接查询数据库 (不含缓存逻辑).
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::queryByUniqueConditions()
     *
     * @param  \Illuminate\Support\Collection  $conditions  条件数组 [['id' => 1], ['id' => 2], ...]
     * @return \Illuminate\Support\Collection 以缓存键为 key 的结果集
     */
    public function queryByUniqueConditions(\Illuminate\Support\Collection $conditions)
    {
    }

    /**
     * 根据 ParameterBag 条件执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBag()
     *
     * @param  bool|int  $when  条件
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     */
    public function whenParameterBag($when, \HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 包含指定键时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagHas()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagHas(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 不包含指定键时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagNotHas()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagNotHas(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 指定键为 null 时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagNull()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagNull(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 指定键不为 null 时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagNotNull()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagNotNull(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 指定键为空时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagEmpty()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagEmpty(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 当 ParameterBag 指定键不为空时执行查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whenParameterBagNotEmpty()
     *
     * @param  \HughCube\Laravel\Knight\Support\ParameterBag  $bag  参数包
     * @param  string|int  $key  参数键名
     * @param  callable  $callable  回调函数
     * @return $this
     *
     * @deprecated
     */
    public function whenParameterBagNotEmpty(\HughCube\Laravel\Knight\Support\ParameterBag $bag, $key, callable $callable)
    {
    }

    /**
     * 按软删除列筛选.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereDeletedAtColumn()
     *
     * @param  mixed  $value  值, null 表示 whereNull
     * @return $this
     */
    public function whereDeletedAtColumn($value = null)
    {
    }

    /**
     * 模糊查询：使用 LIKE 模式匹配.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  LIKE 模式
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件 (and/or)
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     */
    public function whereLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * 左模糊查询：匹配以指定模式开头的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereLeftLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  模式值
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     *
     * @deprecated 使用 whereEscapeLeftLike 代替
     */
    public function whereLeftLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * 右模糊查询：匹配以指定模式结尾的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereRightLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  模式值
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     *
     * @deprecated 使用 whereEscapeRightLike 代替
     */
    public function whereRightLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * OR 模糊查询：使用 LIKE 模式匹配.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  LIKE 模式
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     *
     * @deprecated 使用 orWhereEscapeLike 代替
     */
    public function orWhereLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * OR 左模糊查询：匹配以指定模式开头的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereLeftLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  模式值
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     *
     * @deprecated 使用 orWhereEscapeLeftLike 代替
     */
    public function orWhereLeftLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * OR 右模糊查询：匹配以指定模式结尾的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereRightLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  模式值
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     *
     * @deprecated 使用 orWhereEscapeRightLike 代替
     */
    public function orWhereRightLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * 模糊查询：转义通配符并进行包含匹配.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereEscapeLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     */
    public function whereEscapeLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * OR 模糊查询：转义通配符并进行包含匹配.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereEscapeLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     */
    public function orWhereEscapeLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * 左模糊查询：转义通配符并匹配以指定值开头的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereEscapeLeftLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     */
    public function whereEscapeLeftLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * OR 左模糊查询：转义通配符并匹配以指定值开头的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereEscapeLeftLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     */
    public function orWhereEscapeLeftLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * 右模糊查询：转义通配符并匹配以指定值结尾的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereEscapeRightLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT LIKE
     * @return $this
     */
    public function whereEscapeRightLike(string $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * OR 右模糊查询：转义通配符并匹配以指定值结尾的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereEscapeRightLike()
     *
     * @param  string  $column  列名
     * @param  string  $value  搜索值 (会自动转义特殊字符)
     * @param  bool  $caseSensitive  是否区分大小写
     * @return $this
     */
    public function orWhereEscapeRightLike(string $column, string $value, bool $caseSensitive = false)
    {
    }

    /**
     * 范围查询：匹配指定范围内的记录.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereRange()
     *
     * @param  string  $column  列名
     * @param  iterable  $values  范围值, 支持 [start, end]、['start' => x, 'end' => y] 或 CarbonPeriod
     * @param  string  $boolean  连接条件
     * @param  bool  $not  是否 NOT
     * @return $this
     */
    public function whereRange(string $column, $values, string $boolean = 'and', bool $not = false)
    {
    }

    /**
     * OR 范围查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereRange()
     *
     * @param  string  $column  列名
     * @param  iterable  $values  范围值
     * @return $this
     */
    public function orWhereRange(string $column, $values)
    {
    }

    /**
     * 排除范围查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::whereNotRange()
     *
     * @param  string  $column  列名
     * @param  iterable  $values  范围值
     * @return $this
     */
    public function whereNotRange(string $column, $values)
    {
    }

    /**
     * OR 排除范围查询.
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::orWhereNotRange()
     *
     * @param  string  $column  列名
     * @param  iterable  $values  范围值
     * @return $this
     */
    public function orWhereNotRange(string $column, $values)
    {
    }

    /**
     * 更新记录 (支持乐观锁检查).
     *
     * @see \HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder::update()
     *
     * @param  array  $values  更新值
     * @return int 影响的行数
     */
    public function update(array $values)
    {
    }
}
