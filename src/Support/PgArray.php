<?php

namespace HughCube\Laravel\Knight\Support;

use HughCube\Base\Base;
use Illuminate\Support\Collection;

class PgArray
{
    /**
     * 解析 int[]: {1,2,3} → [1,2,3]
     * 注意：为避免 bigint 溢出，超过 PHP_INT_MAX 的值保持为字符串.
     *
     * @param  mixed  $value
     * @return Collection
     */
    public static function parseIntArray($value): Collection
    {
        if (! is_string($value) || $value === '' || $value === '{}') {
            return Collection::make();
        }

        return Collection::make(explode(',', trim($value, '{}')))->map(function ($v) {
            $v = trim($v);
            if (is_numeric($v) && static::isIntegerInPhpRange($v)) {
                return (int) $v;
            }

            return $v;
        });
    }

    /**
     * 解析简单 text[]: {o:1,o:2} → ['o:1','o:2']
     * 仅支持字母、数字、冒号、下划线、点、连字符.
     *
     * @param  mixed  $value
     * @return Collection
     */
    public static function parseSimpleTextArray($value): Collection
    {
        if (! is_string($value) || $value === '' || $value === '{}') {
            return Collection::make();
        }

        return Collection::make(explode(',', trim($value, '{}')));
    }

    /**
     * 序列化 int[]: [1,2,3] → {1,2,3}.
     *
     * @param  mixed  $value
     * @return string
     */
    public static function serializeIntArray($value): string
    {
        return sprintf('{%s}', Collection::make($value)->filter(fn ($v) => Base::isInteger($v))->map(fn ($v) => Base::toString($v))->implode(','));
    }

    /**
     * 序列化简单 text[]: ['o:1','o:2'] → {o:1,o:2}
     * 支持字母、数字、冒号、下划线、点、连字符、斜杠、@、+、=、#、~
     * 不支持: 空格、逗号、花括号、引号、反斜杠（这些是 PostgreSQL 数组特殊字符）.
     *
     * @param  mixed  $value
     * @return string
     */
    public static function serializeSimpleTextArray($value): string
    {
        return sprintf('{%s}', Collection::make($value)->filter(fn ($v) => is_string($v) && preg_match('/^[a-zA-Z0-9:_.\/\-@+=#~]+$/', $v))->implode(','));
    }

    /**
     * 判断数值字符串是否在 PHP int 范围内
     * 兼容没有安装 BC Math 扩展的环境.
     */
    public static function isIntegerInPhpRange(string $value): bool
    {
        if (function_exists('bccomp')) {
            return bccomp($value, Base::toString(PHP_INT_MIN), 0) >= 0
                && bccomp($value, Base::toString(PHP_INT_MAX), 0) <= 0;
        }

        $value = ltrim($value, '+');
        $isNegative = isset($value[0]) && $value[0] === '-';
        $absValue = $isNegative ? substr($value, 1) : $value;
        $absValue = ltrim($absValue, '0') ?: '0';

        $maxAbs = $isNegative ? substr(Base::toString(PHP_INT_MIN), 1) : Base::toString(PHP_INT_MAX);
        $maxLen = strlen($maxAbs);

        $absLen = strlen($absValue);
        if ($absLen < $maxLen) {
            return true;
        }
        if ($absLen > $maxLen) {
            return false;
        }

        return strcmp($absValue, $maxAbs) <= 0;
    }
}
