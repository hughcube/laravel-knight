<?php

namespace HughCube\Laravel\Knight\Database\Eloquent\Traits;

use HughCube\Laravel\Knight\Support\PgArray;
use Illuminate\Support\Collection;

/**
 * PostgreSQL 原生数组字段辅助 Trait.
 *
 * 支持格式: {1,2,3} 和 {o:1,o:2}
 *
 * 使用方式:
 * 1. 在 Model 中使用此 Trait
 * 2. 默认会从 $casts 中移除值为 'ARRAY' (全大写) 的项(因为 PostgreSQL 原生数组不需要 Laravel 的 cast)
 * 3. 如需保留 'ARRAY' casts，可在 Model 中定义 shouldPreserveUppercaseArrayCast() 方法返回 true
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasPgArrayAttributes
{
    /**
     * 初始化 Trait，在 Model boot 时自动调用
     * 默认移除 casts 中值为 'ARRAY' (全大写) 的项
     * 如 Model 定义了 shouldPreserveUppercaseArrayCast() 方法且返回 true 则跳过移除.
     */
    public function initializeHasPgArrayAttributes(): void
    {
        if (! method_exists($this, 'shouldPreserveUppercaseArrayCast') || ! $this->shouldPreserveUppercaseArrayCast()) {
            $this->casts = array_filter($this->casts, fn ($cast) => $cast !== 'ARRAY');
        }
    }

    /**
     * 解析 int[]: {1,2,3} → [1,2,3]
     * 兼容非字符串输入（array/Collection），直接包装为 Collection 返回.
     * 注意：为避免 bigint 溢出，超过 PHP_INT_MAX 的值保持为字符串.
     */
    protected function parsePgIntArray($value): Collection
    {
        if (! is_string($value)) {
            return Collection::make(is_iterable($value) ? $value : []);
        }

        return PgArray::parseIntArray($value);
    }

    /**
     * 判断数值字符串是否在 PHP int 范围内
     * 兼容没有安装 BC Math 扩展的环境.
     */
    protected function isIntegerInPhpRange(string $value): bool
    {
        return PgArray::isIntegerInPhpRange($value);
    }

    /**
     * 解析简单 text[]: {o:1,o:2} → ['o:1','o:2']
     * 兼容非字符串输入（array/Collection），直接包装为 Collection 返回.
     * 仅支持字母、数字、冒号、下划线、点、连字符.
     */
    protected function parsePgSimpleTextArray($value): Collection
    {
        if (! is_string($value)) {
            return Collection::make(is_iterable($value) ? $value : []);
        }

        return PgArray::parseSimpleTextArray($value);
    }

    /**
     * 序列化 int[]: [1,2,3] → {1,2,3}
     * 兼容字符串输入（PG 格式如 {1,2,3}），先解析再序列化.
     */
    protected function serializePgIntArray($value): string
    {
        if (is_string($value)) {
            $value = PgArray::parseIntArray($value);
        }

        return PgArray::serializeIntArray($value);
    }

    /**
     * 序列化简单 text[]: ['o:1','o:2'] → {o:1,o:2}
     * 兼容字符串输入（PG 格式如 {o:1,o:2}），先解析再序列化.
     * 支持字母、数字、冒号、下划线、点、连字符、斜杠、@、+、=、#、~
     * 不支持: 空格、逗号、花括号、引号、反斜杠（这些是 PostgreSQL 数组特殊字符）.
     */
    protected function serializePgSimpleTextArray($value): string
    {
        if (is_string($value)) {
            $value = PgArray::parseSimpleTextArray($value);
        }

        return PgArray::serializeSimpleTextArray($value);
    }
}
