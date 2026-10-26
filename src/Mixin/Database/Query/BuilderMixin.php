<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/8/3
 * Time: 15:03.
 */

namespace HughCube\Laravel\Knight\Mixin\Database\Query;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Laravel Query Builder 的 PostgreSQL 数组查询扩展 Mixin.
 *
 * 为 Laravel Query Builder 提供 PostgreSQL 数组类型的查询条件扩展方法。
 * 支持三种数组操作符：
 *   - Contains (@>): 包含查询 - 字段数组包含指定值的所有元素
 *   - ContainedBy (<@): 被包含查询 - 字段数组的所有元素都在指定值中
 *   - Overlaps (&&): 交集查询 - 字段数组与指定值有共同元素
 *
 * 使用方式:
 *   在 ServiceProvider 中注册: Builder::mixin(new BuilderMixin());
 *
 * 安全说明:
 *   - 所有值都通过 Laravel 的参数绑定机制处理，防止 SQL 注入
 *   - 列名通过 Grammar 的标准方法转义处理
 *
 * @mixin-target \Illuminate\Database\Query\Builder
 *
 * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin 对应的 Grammar 扩展
 */
class BuilderMixin
{
    // ==================== INTEGER Array Methods ====================

    /**
     * 添加 PostgreSQL INTEGER[] 数组包含查询条件 (@>).
     *
     * 查询数据库字段(数组)是否包含指定的所有元素。
     * 使用 PostgreSQL 的 @> 操作符。
     *
     * 示例:
     *   假设 tags 字段存储 {1, 2, 3, 4, 5}
     *   - whereIntArrayContains('tags', [1, 2])  -> 匹配 (字段包含1和2)
     *   - whereIntArrayContains('tags', [1, 6])  -> 不匹配 (字段不包含6)
     *   - whereIntArrayContains('tags', [])      -> 匹配 (空数组被任意数组包含)
     *
     * SQL: WHERE tags @> ARRAY[1, 2]::integer[]
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'IntArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * 添加 OR 条件的 INTEGER[] 数组包含查询.
     *
     * @see whereIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * 添加 NOT 条件的 INTEGER[] 数组包含查询.
     *
     * @see whereIntArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereIntArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * 添加 OR NOT 条件的 INTEGER[] 数组包含查询.
     *
     * @see whereIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL INTEGER[] 数组被包含查询条件 (<@).
     *
     * 查询数据库字段(数组)的所有元素是否都在指定值中。
     * 使用 PostgreSQL 的 <@ 操作符。
     *
     * 示例:
     *   假设 tags 字段存储 {1, 2}
     *   - whereIntArrayContainedBy('tags', [1, 2, 3])  -> 匹配 (字段所有元素都在查询值中)
     *   - whereIntArrayContainedBy('tags', [1])       -> 不匹配 (字段包含2，但查询值没有)
     *   - whereIntArrayContainedBy('tags', [])        -> 不匹配 (空数组不包含任何元素)
     *
     * SQL: WHERE tags <@ ARRAY[1, 2, 3]::integer[]
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'IntArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * 添加 OR 条件的 INTEGER[] 数组被包含查询.
     *
     * @see whereIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * 添加 NOT 条件的 INTEGER[] 数组被包含查询.
     *
     * @see whereIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereIntArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * 添加 OR NOT 条件的 INTEGER[] 数组被包含查询.
     *
     * @see whereIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL INTEGER[] 数组交集查询条件 (&&).
     *
     * 查询数据库字段(数组)与指定值是否有共同元素(交集非空)。
     * 使用 PostgreSQL 的 && 操作符。
     *
     * 示例:
     *   假设 tags 字段存储 {1, 2, 3}
     *   - whereIntArrayOverlaps('tags', [3, 4, 5])  -> 匹配 (有共同元素3)
     *   - whereIntArrayOverlaps('tags', [4, 5, 6])  -> 不匹配 (无共同元素)
     *   - whereIntArrayOverlaps('tags', [])         -> 不匹配 (空数组无交集)
     *
     * 典型场景: 查询包含任意一个指定标签的记录
     *
     * SQL: WHERE tags && ARRAY[3, 4, 5]::integer[]
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'IntArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * 添加 OR 条件的 INTEGER[] 数组交集查询.
     *
     * @see whereIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * 添加 NOT 条件的 INTEGER[] 数组交集查询.
     *
     * @see whereIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereIntArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * 添加 OR NOT 条件的 INTEGER[] 数组交集查询.
     *
     * @see whereIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereIntArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== BIGINT Array Methods ====================

    /**
     * 添加 PostgreSQL BIGINT[] 数组包含查询条件 (@>).
     *
     * 用于大整数数组字段，如用户ID列表、大型标识符等。
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBigIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BigIntArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBigIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBigIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBigIntArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBigIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBigIntArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBigIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBigIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL BIGINT[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBigIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BigIntArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBigIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBigIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBigIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBigIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBigIntArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBigIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBigIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL BIGINT[] 数组交集查询条件 (&&).
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBigIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BigIntArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBigIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBigIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBigIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBigIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBigIntArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBigIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBigIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBigIntArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== SMALLINT Array Methods ====================

    /**
     * 添加 PostgreSQL SMALLINT[] 数组包含查询条件 (@>).
     *
     * 用于小整数数组字段，如状态码列表、小型枚举值等。
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereSmallIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'SmallIntArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereSmallIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereSmallIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereSmallIntArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotSmallIntArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereSmallIntArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereSmallIntArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotSmallIntArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL SMALLINT[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereSmallIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'SmallIntArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereSmallIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereSmallIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereSmallIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotSmallIntArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereSmallIntArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereSmallIntArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotSmallIntArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL SMALLINT[] 数组交集查询条件 (&&).
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereSmallIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'SmallIntArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereSmallIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereSmallIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereSmallIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotSmallIntArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereSmallIntArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereSmallIntArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotSmallIntArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereSmallIntArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== TEXT Array Methods ====================

    /**
     * 添加 PostgreSQL TEXT[] 数组包含查询条件 (@>).
     *
     * 用于文本数组字段，如标签列表、关键词等。
     *
     * 示例:
     *   假设 tags 字段存储 {'php', 'laravel', 'postgresql'}
     *   - whereTextArrayContains('tags', ['php', 'laravel'])  -> 匹配
     *   - whereTextArrayContains('tags', ['php', 'mysql'])    -> 不匹配
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereTextArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'TextArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereTextArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereTextArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereTextArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotTextArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereTextArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereTextArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotTextArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL TEXT[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereTextArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'TextArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereTextArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereTextArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereTextArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotTextArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereTextArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereTextArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotTextArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL TEXT[] 数组交集查询条件 (&&).
     *
     * 典型场景: 查询包含任意一个指定标签的文章
     *
     * 示例:
     *   假设 tags 字段存储 {'php', 'laravel'}
     *   - whereTextArrayOverlaps('tags', ['php', 'python'])  -> 匹配 (有共同元素 php)
     *   - whereTextArrayOverlaps('tags', ['python', 'go'])   -> 不匹配
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereTextArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'TextArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereTextArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereTextArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereTextArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotTextArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereTextArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereTextArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotTextArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereTextArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== BOOLEAN Array Methods ====================

    /**
     * 添加 PostgreSQL BOOLEAN[] 数组包含查询条件 (@>).
     *
     * 用于布尔值数组字段，如权限标志列表等。
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBooleanArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BooleanArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBooleanArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBooleanArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBooleanArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBooleanArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBooleanArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBooleanArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBooleanArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL BOOLEAN[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBooleanArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BooleanArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBooleanArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBooleanArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBooleanArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBooleanArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBooleanArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBooleanArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBooleanArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL BOOLEAN[] 数组交集查询条件 (&&).
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereBooleanArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'BooleanArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereBooleanArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereBooleanArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereBooleanArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotBooleanArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereBooleanArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereBooleanArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotBooleanArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereBooleanArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== DOUBLE PRECISION Array Methods ====================

    /**
     * 添加 PostgreSQL DOUBLE PRECISION[] 数组包含查询条件 (@>).
     *
     * 用于双精度浮点数数组字段，如坐标点列表、精确数值等。
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereDoubleArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'DoubleArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereDoubleArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereDoubleArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereDoubleArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotDoubleArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereDoubleArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereDoubleArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotDoubleArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL DOUBLE PRECISION[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereDoubleArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'DoubleArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereDoubleArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereDoubleArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereDoubleArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotDoubleArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereDoubleArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereDoubleArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotDoubleArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL DOUBLE PRECISION[] 数组交集查询条件 (&&).
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereDoubleArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'DoubleArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereDoubleArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereDoubleArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereDoubleArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotDoubleArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereDoubleArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereDoubleArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotDoubleArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereDoubleArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== REAL (Float) Array Methods ====================

    /**
     * 添加 PostgreSQL REAL[] (单精度浮点) 数组包含查询条件 (@>).
     *
     * 用于单精度浮点数数组字段，节省存储空间。
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereFloatArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'FloatArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereFloatArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereFloatArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereFloatArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotFloatArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereFloatArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereFloatArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotFloatArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL REAL[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereFloatArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'FloatArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereFloatArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereFloatArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereFloatArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotFloatArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereFloatArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereFloatArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotFloatArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL REAL[] 数组交集查询条件 (&&).
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereFloatArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'FloatArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereFloatArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereFloatArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereFloatArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotFloatArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereFloatArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereFloatArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotFloatArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereFloatArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== UUID Array Methods ====================

    /**
     * 添加 PostgreSQL UUID[] 数组包含查询条件 (@>).
     *
     * 用于 UUID 数组字段，如关联的资源ID列表等。
     *
     * 示例:
     *   假设 resource_ids 字段存储 {'550e8400-e29b-41d4-a716-446655440000', '6ba7b810-9dad-11d1-80b4-00c04fd430c8'}
     *   - whereUuidArrayContains('resource_ids', ['550e8400-e29b-41d4-a716-446655440000'])  -> 匹配
     *
     * @see whereIntArrayContains() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereUuidArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'UuidArrayContains';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereUuidArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereUuidArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayContains($column, $value, 'or', false);
        };
    }

    /**
     * @see whereUuidArrayContains()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotUuidArrayContains(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereUuidArrayContains($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereUuidArrayContains()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotUuidArrayContains(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayContains($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL UUID[] 数组被包含查询条件 (<@).
     *
     * @see whereIntArrayContainedBy() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereUuidArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'UuidArrayContainedBy';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereUuidArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereUuidArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayContainedBy($column, $value, 'or', false);
        };
    }

    /**
     * @see whereUuidArrayContainedBy()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotUuidArrayContainedBy(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereUuidArrayContainedBy($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereUuidArrayContainedBy()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotUuidArrayContainedBy(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayContainedBy($column, $value, 'or', true);
        };
    }

    /**
     * 添加 PostgreSQL UUID[] 数组交集查询条件 (&&).
     *
     * 典型场景: 查询关联了任意一个指定资源的记录
     *
     * @see whereIntArrayOverlaps() 使用方式相同
     *
     * @return Closure(string $column, array $value, string $boolean = 'and', bool $not = false): \Illuminate\Database\Query\Builder
     */
    public function whereUuidArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and', $not = false): Builder {
            $type = 'UuidArrayOverlaps';
            $valueArray = $value instanceof Arrayable ? $value->toArray() : (array) $value;

            $this->wheres[] = ['type' => $type, 'column' => $column, 'value' => $valueArray, 'boolean' => $boolean, 'not' => $not];

            if (! $value instanceof Expression) {
                foreach ($valueArray as $val) {
                    $this->addBinding($val);
                }
            }

            return $this;
        };
    }

    /**
     * @see whereUuidArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereUuidArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayOverlaps($column, $value, 'or', false);
        };
    }

    /**
     * @see whereUuidArrayOverlaps()
     *
     * @return Closure(string $column, array $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereNotUuidArrayOverlaps(): Closure
    {
        return function ($column, $value, $boolean = 'and'): Builder {
            return $this->whereUuidArrayOverlaps($column, $value, $boolean, true);
        };
    }

    /**
     * @see whereUuidArrayOverlaps()
     *
     * @return Closure(string $column, array $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereNotUuidArrayOverlaps(): Closure
    {
        return function ($column, $value): Builder {
            return $this->whereUuidArrayOverlaps($column, $value, 'or', true);
        };
    }

    // ==================== Array Length Methods ====================

    /**
     * 添加 PostgreSQL 数组长度查询条件.
     *
     * 使用 PostgreSQL 的 array_length() 函数查询数组长度。
     *
     * 示例:
     *   假设 tags 字段存储 {'php', 'laravel', 'mysql'}
     *   - whereArrayLength('tags', '>', 2)   -> 匹配 (长度3 > 2)
     *   - whereArrayLength('tags', '=', 3)   -> 匹配 (长度3 = 3)
     *   - whereArrayLength('tags', '<', 2)   -> 不匹配 (长度3 < 2)
     *
     * 注意: array_length() 对空数组返回 NULL，使用 COALESCE 处理
     *
     * SQL: WHERE COALESCE(array_length(col, 1), 0) > ?
     *
     * @return Closure(string $column, string $operator, int $value, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereArrayLength(): Closure
    {
        return function ($column, $operator, $value, $boolean = 'and'): Builder {
            $type = 'ArrayLength';

            $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

            if (! $value instanceof Expression) {
                $this->addBinding($value);
            }

            return $this;
        };
    }

    /**
     * 添加 OR 条件的数组长度查询.
     *
     * @see whereArrayLength()
     *
     * @return Closure(string $column, string $operator, int $value): \Illuminate\Database\Query\Builder
     */
    public function orWhereArrayLength(): Closure
    {
        return function ($column, $operator, $value): Builder {
            return $this->whereArrayLength($column, $operator, $value, 'or');
        };
    }

    // ==================== Array Empty Check Methods ====================

    /**
     * 添加 PostgreSQL 数组为空查询条件.
     *
     * 使用 cardinality() 函数检查数组是否为空。
     * 同时检查 NULL 和空数组两种情况。
     *
     * 示例:
     *   - whereArrayIsEmpty('tags')  -> 匹配 NULL 或 {}
     *
     * SQL: WHERE (cardinality(col) = 0 OR col IS NULL)
     *
     * @return Closure(string $column, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereArrayIsEmpty(): Closure
    {
        return function ($column, $boolean = 'and'): Builder {
            $type = 'ArrayIsEmpty';
            $not = false;

            $this->wheres[] = compact('type', 'column', 'boolean', 'not');

            return $this;
        };
    }

    /**
     * 添加 OR 条件的数组为空查询.
     *
     * @see whereArrayIsEmpty()
     *
     * @return Closure(string $column): \Illuminate\Database\Query\Builder
     */
    public function orWhereArrayIsEmpty(): Closure
    {
        return function ($column): Builder {
            return $this->whereArrayIsEmpty($column, 'or');
        };
    }

    /**
     * 添加 PostgreSQL 数组非空查询条件.
     *
     * 使用 cardinality() 函数检查数组是否非空。
     *
     * 示例:
     *   - whereArrayIsNotEmpty('tags')  -> 匹配有元素的数组
     *
     * SQL: WHERE cardinality(col) > 0
     *
     * @return Closure(string $column, string $boolean = 'and'): \Illuminate\Database\Query\Builder
     */
    public function whereArrayIsNotEmpty(): Closure
    {
        return function ($column, $boolean = 'and'): Builder {
            $type = 'ArrayIsEmpty';
            $not = true;

            $this->wheres[] = compact('type', 'column', 'boolean', 'not');

            return $this;
        };
    }

    /**
     * 添加 OR 条件的数组非空查询.
     *
     * @see whereArrayIsNotEmpty()
     *
     * @return Closure(string $column): \Illuminate\Database\Query\Builder
     */
    public function orWhereArrayIsNotEmpty(): Closure
    {
        return function ($column): Builder {
            return $this->whereArrayIsNotEmpty($column, 'or');
        };
    }

    // ==================== Debug Methods ====================

    /**
     * 输出完整的 SQL 语句并终止程序.
     *
     * 获取带有绑定值的完整 SQL 后直接 die。
     * 用于调试查询时快速查看生成的 SQL。
     *
     * 示例:
     *   User::query()->where('id', 1)->dieRawSql();
     *   // 输出: select * from "users" where "id" = 1
     *   // 然后终止程序
     *
     * @return Closure(): never
     */
    public function dieRawSql(): Closure
    {
        return function (): void {
            exit($this->toRawSql());
        };
    }
}
