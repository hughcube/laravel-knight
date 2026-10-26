<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/8/3
 * Time: 15:01.
 */

namespace HughCube\Laravel\Knight\Mixin\Database\Query\Grammars;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

/**
 * Laravel SQL Grammar 的扩展 Mixin.
 *
 * 为 Laravel Query Grammar 提供额外的 SQL 编译方法扩展。
 *
 * 使用方式:
 *   在 ServiceProvider 中注册: Grammar::mixin(new GrammarMixin());
 *
 * 安全说明:
 *   - 使用 $this->parameter() 生成参数占位符，防止 SQL 注入
 *   - 列名通过 wrapJsonFieldAndPath() 正确转义处理
 *   - 遵循 Laravel 的标准 Grammar 编译模式
 *
 * @mixin-target \Illuminate\Database\Query\Grammars\Grammar
 *
 * @property-read Grammar $connection
 *
 * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin 对应的 Builder 扩展
 */
class GrammarMixin
{
    // ==================== INTEGER Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL INTEGER[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereIntArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::integer[]' : 'ARRAY[]::integer[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL INTEGER[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereIntArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::integer[]' : 'ARRAY[]::integer[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL INTEGER[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereIntArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::integer[]' : 'ARRAY[]::integer[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== BIGINT Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL BIGINT[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBigIntArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::bigint[]' : 'ARRAY[]::bigint[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL BIGINT[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBigIntArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::bigint[]' : 'ARRAY[]::bigint[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL BIGINT[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBigIntArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::bigint[]' : 'ARRAY[]::bigint[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== SMALLINT Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL SMALLINT[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereSmallIntArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::smallint[]' : 'ARRAY[]::smallint[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL SMALLINT[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereSmallIntArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::smallint[]' : 'ARRAY[]::smallint[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL SMALLINT[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereSmallIntArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::smallint[]' : 'ARRAY[]::smallint[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== TEXT Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL TEXT[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereTextArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::text[]' : 'ARRAY[]::text[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL TEXT[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereTextArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::text[]' : 'ARRAY[]::text[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL TEXT[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereTextArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::text[]' : 'ARRAY[]::text[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== BOOLEAN Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL BOOLEAN[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBooleanArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::boolean[]' : 'ARRAY[]::boolean[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL BOOLEAN[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBooleanArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::boolean[]' : 'ARRAY[]::boolean[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL BOOLEAN[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereBooleanArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::boolean[]' : 'ARRAY[]::boolean[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== DOUBLE PRECISION Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereDoubleArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::double precision[]' : 'ARRAY[]::double precision[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereDoubleArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::double precision[]' : 'ARRAY[]::double precision[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereDoubleArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::double precision[]' : 'ARRAY[]::double precision[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== REAL (Float) Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL REAL[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereFloatArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::real[]' : 'ARRAY[]::real[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL REAL[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereFloatArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::real[]' : 'ARRAY[]::real[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL REAL[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereFloatArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::real[]' : 'ARRAY[]::real[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== UUID Array Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL UUID[] 数组包含条件 (@>) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereUuidArrayContains(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::uuid[]' : 'ARRAY[]::uuid[]';

            return $not.'('.$column.' @> '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL UUID[] 数组被包含条件 (<@) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereUuidArrayContainedBy(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::uuid[]' : 'ARRAY[]::uuid[]';

            return $not.'('.$column.' <@ '.$arrayExpr.')';
        };
    }

    /**
     * 编译 WHERE PostgreSQL UUID[] 数组重叠条件 (&&) 为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereUuidArrayOverlaps(): Closure
    {
        return function (Builder $query, $where) {
            $not = $where['not'] ? 'not ' : '';
            $column = $this->wrap($where['column']);
            $value = $where['value'];
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;
            $count = count($valueArray);
            $placeholders = $count > 0 ? implode(', ', array_fill(0, $count, '?')) : '';
            $arrayExpr = $count > 0 ? 'ARRAY['.$placeholders.']::uuid[]' : 'ARRAY[]::uuid[]';

            return $not.'('.$column.' && '.$arrayExpr.')';
        };
    }

    // ==================== Array Length Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL 数组长度条件为 SQL 片段.
     *
     * 使用 COALESCE 处理空数组返回 NULL 的情况。
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereArrayLength(): Closure
    {
        return function (Builder $query, $where) {
            $column = $this->wrap($where['column']);
            $operator = $where['operator'];

            // Validate operator
            $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>='];
            if (! in_array($operator, $validOperators, true)) {
                $operator = '=';
            }

            return 'COALESCE(array_length('.$column.', 1), 0) '.$operator.' ?';
        };
    }

    // ==================== Array Empty Check Grammar Methods ====================

    /**
     * 编译 WHERE PostgreSQL 数组空检查条件为 SQL 片段.
     *
     * @return Closure(Builder $query, array $where): string
     */
    public function whereArrayIsEmpty(): Closure
    {
        return function (Builder $query, $where) {
            $column = $this->wrap($where['column']);
            $not = $where['not'] ?? false;

            if ($not) {
                // 非空: cardinality > 0
                return 'cardinality('.$column.') > 0';
            }

            // 为空: cardinality = 0 OR IS NULL
            return '(cardinality('.$column.') = 0 OR '.$column.' IS NULL)';
        };
    }
}
