<?php
/* @noinspection ALL */
// @formatter:off
// phpcs:ignoreFile

/**
 * IDE helper for App Mixin methods.
 *
 * 此文件由 ide:generate-mixin-helper 命令自动生成，请勿手动修改。
 */

namespace Illuminate\Database\Schema {

    /**
     * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin
     */
    class Blueprint
    {
        /**
         * 添加 Knight 常用字段(反顺序).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightColumnsReversed()
         */
        public function knightColumnsReversed(): mixed
        {
        }

        /**
         * 添加 Knight 常用字段.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightColumns()
         */
        public function knightColumns(): mixed
        {
        }

        /**
         * 创建 GIN 索引 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightGin()
         * @return static
         */
        public function knightGin($columns, ?string $indexName = null): static
        {
        }

        /**
         * 创建条件 GIN 索引 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightGinWhere()
         * @return static
         */
        public function knightGinWhere($columns, string $whereCondition, ?string $indexName = null): static
        {
        }

        /**
         * 创建条件 GIN 索引，仅对未软删除的记录生效 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightGinWhereNotDeleted()
         */
        public function knightGinWhereNotDeleted($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at'): mixed
        {
        }

        /**
         * 创建条件唯一索引 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightUniqueWhere()
         * @return static
         */
        public function knightUniqueWhere($columns, string $whereCondition, ?string $indexName = null): static
        {
        }

        /**
         * 创建条件索引 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightIndexWhere()
         */
        public function knightIndexWhere($columns, string $whereCondition, ?string $indexName = null): mixed
        {
        }

        /**
         * 创建条件唯一索引，仅对未软删除的记录生效 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightUniqueWhereNotDeleted()
         */
        public function knightUniqueWhereNotDeleted($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at'): mixed
        {
        }

        /**
         * 创建条件索引，仅对未软删除的记录生效 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightIndexWhereNotDeleted()
         */
        public function knightIndexWhereNotDeleted($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at'): mixed
        {
        }

        /**
         * 添加 INTEGER[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightIntArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightIntArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 BIGINT[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightBigIntArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightBigIntArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 SMALLINT[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightSmallIntArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightSmallIntArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 TEXT[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightTextArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightTextArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 VARCHAR(n)[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightVarcharArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightVarcharArray(string $column, int $length = 255): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 BOOLEAN[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightBooleanArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightBooleanArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 DOUBLE PRECISION[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightDoubleArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightDoubleArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 REAL[] (单精度浮点) 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightFloatArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightFloatArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 UUID[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightUuidArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightUuidArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 NUMERIC[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightNumericArray()
         */
        public function knightNumericArray(string $column, ?int $precision = null, ?int $scale = null): mixed
        {
        }

        /**
         * 添加 TIMESTAMPTZ[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightTimestamptzArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightTimestamptzArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 DATE[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightDateArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightDateArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 JSONB[] 数组列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightJsonbArray()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightJsonbArray(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 TSVECTOR 列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightTsVector()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightTsVector(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 添加 TSQUERY 列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightTsQuery()
         * @return \Illuminate\Database\Schema\ColumnDefinition
         */
        public function knightTsQuery(string $column): \Illuminate\Database\Schema\ColumnDefinition
        {
        }

        /**
         * 设置序列的下一个值 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightSetSequenceValue()
         * @return static
         */
        public function knightSetSequenceValue(string $column, int $value, ?string $sequenceName = null): static
        {
        }

        /**
         * 重启序列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightRestartSequence()
         * @return static
         */
        public function knightRestartSequence(string $column, int $value, ?string $sequenceName = null): static
        {
        }

        /**
         * 创建一个独立的序列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightCreateSequence()
         */
        public function knightCreateSequence(string $sequenceName, int $startWith = 1, int $incrementBy = 1, ?int $maxValue = null, int $minValue = 1, bool $cycle = false, int $cache = 1): mixed
        {
        }

        /**
         * 删除序列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightDropSequence()
         * @return static
         */
        public function knightDropSequence(string $sequenceName, bool $ifExists = false): static
        {
        }

        /**
         * 添加使用指定序列的 BIGINT 主键列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightIdWithSequence()
         */
        public function knightIdWithSequence(string $column, string $sequenceName, bool $primary = true): mixed
        {
        }

        /**
         * 修改列以使用指定的序列 (PostgreSQL).
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin::knightUseSequence()
         */
        public function knightUseSequence(string $column, string $sequenceName): mixed
        {
        }
    }
}

namespace Illuminate\Database\Schema\Grammars {

    /**
     * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin
     */
    class PostgresGrammar
    {
        /**
         * 编译 GIN 索引命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightGinIndex()
         * @return string
         */
        public function compileKnightGinIndex(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译条件唯一索引命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightUniqueWhere()
         * @return string
         */
        public function compileKnightUniqueWhere(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译条件索引命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightIndexWhere()
         * @return string
         */
        public function compileKnightIndexWhere(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译条件 GIN 索引命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightGinIndexWhere()
         * @return string
         */
        public function compileKnightGinIndexWhere(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 定义 INTEGER[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightIntArray()
         * @return string
         */
        public function typeKnightIntArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 BIGINT[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightBigIntArray()
         * @return string
         */
        public function typeKnightBigIntArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 SMALLINT[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightSmallIntArray()
         * @return string
         */
        public function typeKnightSmallIntArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 TEXT[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightTextArray()
         * @return string
         */
        public function typeKnightTextArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 VARCHAR(n)[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightVarcharArray()
         * @return string
         */
        public function typeKnightVarcharArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 BOOLEAN[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightBooleanArray()
         * @return string
         */
        public function typeKnightBooleanArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 DOUBLE PRECISION[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightDoubleArray()
         * @return string
         */
        public function typeKnightDoubleArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 REAL[] (单精度浮点) 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightFloatArray()
         * @return string
         */
        public function typeKnightFloatArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 UUID[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightUuidArray()
         * @return string
         */
        public function typeKnightUuidArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 NUMERIC[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightNumericArray()
         * @return string
         */
        public function typeKnightNumericArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 TIMESTAMPTZ[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightTimestamptzArray()
         * @return string
         */
        public function typeKnightTimestamptzArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 DATE[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightDateArray()
         * @return string
         */
        public function typeKnightDateArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 JSONB[] 数组列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightJsonbArray()
         * @return string
         */
        public function typeKnightJsonbArray(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 TSVECTOR 列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightTsVector()
         * @return string
         */
        public function typeKnightTsVector(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 定义 TSQUERY 列类型.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::typeKnightTsQuery()
         * @return string
         */
        public function typeKnightTsQuery(\Illuminate\Support\Fluent $column): string
        {
        }

        /**
         * 编译设置序列值命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightSetSequenceValue()
         * @return string
         */
        public function compileKnightSetSequenceValue(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译重启序列命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightRestartSequence()
         * @return string
         */
        public function compileKnightRestartSequence(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译创建序列命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightCreateSequence()
         * @return string
         */
        public function compileKnightCreateSequence(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译删除序列命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightDropSequence()
         * @return string
         */
        public function compileKnightDropSequence(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译使用序列的主键列命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightIdWithSequence()
         * @return string
         */
        public function compileKnightIdWithSequence(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }

        /**
         * 编译修改列使用序列命令.
         * @see \HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin::compileKnightUseSequence()
         * @return string
         */
        public function compileKnightUseSequence(\Illuminate\Database\Schema\Blueprint $blueprint, \Illuminate\Support\Fluent $command): string
        {
        }
    }
}

namespace Illuminate\Database\Query {

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin
     */
    class Builder
    {
        /**
         * 添加 PostgreSQL INTEGER[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereIntArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的 INTEGER[] 数组包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 NOT 条件的 INTEGER[] 数组包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotIntArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR NOT 条件的 INTEGER[] 数组包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL INTEGER[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereIntArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的 INTEGER[] 数组被包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 NOT 条件的 INTEGER[] 数组被包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotIntArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR NOT 条件的 INTEGER[] 数组被包含查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL INTEGER[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereIntArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的 INTEGER[] 数组交集查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 NOT 条件的 INTEGER[] 数组交集查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotIntArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR NOT 条件的 INTEGER[] 数组交集查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BIGINT[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBigIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBigIntArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBigIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBigIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBigIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBigIntArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBigIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBigIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BIGINT[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBigIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBigIntArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBigIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBigIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBigIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBigIntArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBigIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBigIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BIGINT[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBigIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBigIntArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBigIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBigIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBigIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBigIntArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBigIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBigIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL SMALLINT[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereSmallIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereSmallIntArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereSmallIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereSmallIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotSmallIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotSmallIntArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotSmallIntArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotSmallIntArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL SMALLINT[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereSmallIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereSmallIntArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereSmallIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereSmallIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotSmallIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotSmallIntArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotSmallIntArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotSmallIntArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL SMALLINT[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereSmallIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereSmallIntArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereSmallIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereSmallIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotSmallIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotSmallIntArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotSmallIntArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotSmallIntArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL TEXT[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereTextArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereTextArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereTextArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereTextArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotTextArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotTextArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotTextArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotTextArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL TEXT[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereTextArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereTextArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereTextArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereTextArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotTextArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotTextArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotTextArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotTextArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL TEXT[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereTextArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereTextArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereTextArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereTextArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotTextArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotTextArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotTextArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotTextArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BOOLEAN[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBooleanArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBooleanArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBooleanArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBooleanArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBooleanArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBooleanArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBooleanArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBooleanArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BOOLEAN[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBooleanArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBooleanArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBooleanArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBooleanArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBooleanArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBooleanArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBooleanArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBooleanArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL BOOLEAN[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereBooleanArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereBooleanArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereBooleanArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereBooleanArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotBooleanArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotBooleanArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotBooleanArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotBooleanArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL DOUBLE PRECISION[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereDoubleArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereDoubleArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereDoubleArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereDoubleArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotDoubleArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotDoubleArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotDoubleArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotDoubleArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL DOUBLE PRECISION[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereDoubleArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereDoubleArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereDoubleArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereDoubleArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotDoubleArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotDoubleArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotDoubleArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotDoubleArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL DOUBLE PRECISION[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereDoubleArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereDoubleArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereDoubleArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereDoubleArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotDoubleArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotDoubleArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotDoubleArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotDoubleArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL REAL[] (单精度浮点) 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereFloatArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereFloatArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereFloatArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereFloatArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotFloatArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotFloatArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotFloatArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotFloatArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL REAL[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereFloatArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereFloatArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereFloatArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereFloatArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotFloatArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotFloatArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotFloatArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotFloatArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL REAL[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereFloatArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereFloatArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereFloatArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereFloatArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotFloatArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotFloatArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotFloatArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotFloatArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL UUID[] 数组包含查询条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereUuidArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereUuidArrayContains($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereUuidArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereUuidArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotUuidArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotUuidArrayContains($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotUuidArrayContains()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotUuidArrayContains($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL UUID[] 数组被包含查询条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereUuidArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereUuidArrayContainedBy($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereUuidArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereUuidArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotUuidArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotUuidArrayContainedBy($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotUuidArrayContainedBy()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotUuidArrayContainedBy($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL UUID[] 数组交集查询条件 (&&).
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereUuidArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereUuidArrayOverlaps($column, $value, $boolean = 'and', $not = false): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereUuidArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereUuidArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereNotUuidArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereNotUuidArrayOverlaps($column, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereNotUuidArrayOverlaps()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereNotUuidArrayOverlaps($column, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL 数组长度查询条件.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereArrayLength()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereArrayLength($column, $operator, $value, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的数组长度查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereArrayLength()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereArrayLength($column, $operator, $value): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL 数组为空查询条件.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereArrayIsEmpty()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereArrayIsEmpty($column, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的数组为空查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereArrayIsEmpty()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereArrayIsEmpty($column): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 PostgreSQL 数组非空查询条件.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::whereArrayIsNotEmpty()
         * @return \Illuminate\Database\Query\Builder
         */
        public function whereArrayIsNotEmpty($column, $boolean = 'and'): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 添加 OR 条件的数组非空查询.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::orWhereArrayIsNotEmpty()
         * @return \Illuminate\Database\Query\Builder
         */
        public function orWhereArrayIsNotEmpty($column): \Illuminate\Database\Query\Builder
        {
        }

        /**
         * 输出完整的 SQL 语句并终止程序.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\BuilderMixin::dieRawSql()
         * @return never
         */
        public function dieRawSql(): void
        {
        }
    }
}

namespace Illuminate\Database\Query\Grammars {

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin
     */
    class Grammar
    {
        /**
         * 编译 WHERE PostgreSQL INTEGER[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereIntArrayContains()
         * @return string
         */
        public function whereIntArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL INTEGER[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereIntArrayContainedBy()
         * @return string
         */
        public function whereIntArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL INTEGER[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereIntArrayOverlaps()
         * @return string
         */
        public function whereIntArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BIGINT[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBigIntArrayContains()
         * @return string
         */
        public function whereBigIntArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BIGINT[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBigIntArrayContainedBy()
         * @return string
         */
        public function whereBigIntArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BIGINT[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBigIntArrayOverlaps()
         * @return string
         */
        public function whereBigIntArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL SMALLINT[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereSmallIntArrayContains()
         * @return string
         */
        public function whereSmallIntArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL SMALLINT[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereSmallIntArrayContainedBy()
         * @return string
         */
        public function whereSmallIntArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL SMALLINT[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereSmallIntArrayOverlaps()
         * @return string
         */
        public function whereSmallIntArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL TEXT[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereTextArrayContains()
         * @return string
         */
        public function whereTextArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL TEXT[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereTextArrayContainedBy()
         * @return string
         */
        public function whereTextArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL TEXT[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereTextArrayOverlaps()
         * @return string
         */
        public function whereTextArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BOOLEAN[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBooleanArrayContains()
         * @return string
         */
        public function whereBooleanArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BOOLEAN[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBooleanArrayContainedBy()
         * @return string
         */
        public function whereBooleanArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL BOOLEAN[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereBooleanArrayOverlaps()
         * @return string
         */
        public function whereBooleanArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereDoubleArrayContains()
         * @return string
         */
        public function whereDoubleArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereDoubleArrayContainedBy()
         * @return string
         */
        public function whereDoubleArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL DOUBLE PRECISION[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereDoubleArrayOverlaps()
         * @return string
         */
        public function whereDoubleArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL REAL[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereFloatArrayContains()
         * @return string
         */
        public function whereFloatArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL REAL[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereFloatArrayContainedBy()
         * @return string
         */
        public function whereFloatArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL REAL[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereFloatArrayOverlaps()
         * @return string
         */
        public function whereFloatArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL UUID[] 数组包含条件 (
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereUuidArrayContains()
         * @return string
         */
        public function whereUuidArrayContains(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL UUID[] 数组被包含条件 (<
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereUuidArrayContainedBy()
         * @return string
         */
        public function whereUuidArrayContainedBy(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL UUID[] 数组重叠条件 (&&) 为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereUuidArrayOverlaps()
         * @return string
         */
        public function whereUuidArrayOverlaps(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL 数组长度条件为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereArrayLength()
         * @return string
         */
        public function whereArrayLength(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }

        /**
         * 编译 WHERE PostgreSQL 数组空检查条件为 SQL 片段.
         * @see \HughCube\Laravel\Knight\Mixin\Database\Query\Grammars\GrammarMixin::whereArrayIsEmpty()
         * @return string
         */
        public function whereArrayIsEmpty(\Illuminate\Database\Query\Builder $query, $where): string
        {
        }
    }
}

namespace Illuminate\Http {

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin
     */
    class Request
    {
        /**
         * 获取客户端版本.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getClientVersion()
         */
        public function getClientVersion(): ?string
        {
        }

        /**
         * 获取客户端的随机字符串.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getClientNonce()
         */
        public function getClientNonce(): ?string
        {
        }

        /**
         * 获取客户端的签名字符串.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getClientSignature()
         */
        public function getClientSignature(): ?string
        {
        }

        /**
         * 获取客户端的所有请求头.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getClientHeaders()
         */
        public function getClientHeaders(): \Symfony\Component\HttpFoundation\HeaderBag
        {
        }

        /**
         * 获取客户端日期
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getDate()
         */
        public function getDate(): ?string
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getClientDate()
         */
        public function getClientDate(): ?string
        {
        }

        /**
         * 获取agent检测.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getUserAgentDetect()
         */
        public function getUserAgentDetect(): mixed
        {
        }

        /**
         * 判断是否在微信客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isWeChat()
         */
        public function isWeChat(): bool
        {
        }

        /**
         * 判断是否在微信客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isWeChatMiniProgram()
         */
        public function isWeChatMiniProgram(): bool
        {
        }

        /**
         * 判断是否在postmen/Apifox.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isPostmen()
         */
        public function isPostmen(): bool
        {
        }

        /**
         * 判断是否为 API 调试工具.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isApiDebugTool()
         */
        public function isApiDebugTool(): bool
        {
        }

        /**
         * 判断请求是否来自指定版本的客户端.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isEqClientVersion()
         */
        public function isEqClientVersion(string $version, ?int $length = null): bool
        {
        }

        /**
         * 判断请求是否来自大于指定版本的客户端.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isLtClientVersion()
         */
        public function isLtClientVersion(string $version, bool $contain = false, ?int $length = null): bool
        {
        }

        /**
         * 判断请求是否来自小于指定版本的客户端.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isGtClientVersion()
         */
        public function isGtClientVersion(string $version, bool $contain = false, ?int $length = null): bool
        {
        }

        /**
         * 获取最后一级目录.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::getLastDirectory()
         */
        public function getLastDirectory(): ?string
        {
        }

        /**
         * 判断是否在企业微信客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isWeCom()
         */
        public function isWeCom(): bool
        {
        }

        /**
         * 判断是否在钉钉客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isDingTalk()
         */
        public function isDingTalk(): bool
        {
        }

        /**
         * 判断是否在飞书客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isFeishu()
         */
        public function isFeishu(): bool
        {
        }

        /**
         * 判断是否在支付宝客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isAlipay()
         */
        public function isAlipay(): bool
        {
        }

        /**
         * 判断是否在QQ客户端内(非QQ浏览器).
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isQQ()
         */
        public function isQQ(): bool
        {
        }

        /**
         * 判断是否在QQ浏览器内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isQQBrowser()
         */
        public function isQQBrowser(): bool
        {
        }

        /**
         * 判断是否在UC浏览器内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isUCBrowser()
         */
        public function isUCBrowser(): bool
        {
        }

        /**
         * 判断是否在微博客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isWeibo()
         */
        public function isWeibo(): bool
        {
        }

        /**
         * 判断是否在抖音/字节系客户端内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isDouyin()
         */
        public function isDouyin(): bool
        {
        }

        /**
         * 判断是否在Quark浏览器内.
         * @see \HughCube\Laravel\Knight\Mixin\Http\RequestMixin::isQuark()
         */
        public function isQuark(): bool
        {
        }
    }
}

namespace Carbon {

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin
     */
    class Carbon
    {
        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::tryParse()
         * @return \Carbon\Carbon|null
         */
        public function tryParse($date = null, $tz = null): ?\Carbon\Carbon
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::getTimestampAsFloat()
         */
        public function getTimestampAsFloat(): float
        {
        }

        /**
         * Mainly used for BC Math extensions.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::getTimestampAsString()
         */
        public function getTimestampAsString(): string
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::toRfc3339ExtendedString()
         */
        public function toRfc3339ExtendedString(): string
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::toChineseDate()
         */
        public function toChineseDate(): string
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::try()
         */
        public function try(callable $callable, $default = null): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::tryCreateFromFormat()
         */
        public function tryCreateFromFormat($format, $time, $timezone = null): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::tryParseDate()
         */
        public function tryParseDate($date): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CarbonMixin::tryCreateFromFormats()
         */
        public function tryCreateFromFormats($date, $formats): mixed
        {
        }
    }
}

namespace Illuminate\Support {

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin
     */
    class Collection
    {
        /**
         * 根据回调方法检查是否存在指定元素.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::hasByCallable()
         */
        public function hasByCallable(callable $key): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::hasAnyValues()
         */
        public function hasAnyValues($values, bool $strict = false): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::hasAllValues()
         */
        public function hasAllValues($values, bool $strict = false): mixed
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::hasValue()
         */
        public function hasValue($needle, $strict = false): mixed
        {
        }

        /**
         * 是否是索引数组.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::isIndexed()
         */
        public function isIndexed(bool $consecutive = true): mixed
        {
        }

        /**
         * 返回指定元素之后的所有元素.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::afterFirstItems()
         * @return static
         */
        public function afterFirstItems($value = null, $withBeacon = false, $strict = false): static
        {
        }

        /**
         * 返回指定元素之后的所有元素.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::afterLastItems()
         * @return static
         */
        public function afterLastItems($value = null, $withBeacon = false, $strict = false): static
        {
        }

        /**
         * 过滤元素直到满足$stop.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::filterWithStop()
         * @return static
         */
        public function filterWithStop(callable $stop, $withStopItem = false): static
        {
        }

        /**
         * pluck指定set(1,2,3,4)元素, 并且合并后在分割为Collection.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::pluckAndMergeSetColumn()
         * @return static
         */
        public function pluckAndMergeSetColumn($name, $separator = ',', $filter = null): static
        {
        }

        /**
         * 合并指定列中的数组元素.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::pluckAndMergeArrayColumn()
         * @return static
         */
        public function pluckAndMergeArrayColumn($name): static
        {
        }

        /**
         * 收集指定数组keys, 组合成一个新的collection.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::onlyArrayKeys()
         * @return static
         */
        public function onlyArrayKeys($keys = []): static
        {
        }

        /**
         * 收集指定属性的指定值, 组合成一个新的collection.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::onlyColumnValues()
         * @return static
         */
        public function onlyColumnValues($values, $name = null, bool $strict = false): static
        {
        }

        /**
         * 满足条件在执行过滤.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::whenFilter()
         * @return static
         */
        public function whenFilter($when, callable $callable): static
        {
        }

        /**
         * map int.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::mapInt()
         * @return static
         */
        public function mapInt(): static
        {
        }

        /**
         * map string.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::mapString()
         * @return static
         */
        public function mapString(): static
        {
        }

        /**
         * explode.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::explode()
         * @return static
         */
        public function explode(string $separator, string $string, int $limit = 9223372036854775807): static
        {
        }

        /**
         * split.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::splitWhitespace()
         * @return static
         */
        public function splitWhitespace(string $separator, string $pattern = '/\\s+/', int $limit = -1): static
        {
        }

        /**
         * split ,.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::splitComma()
         * @return static
         */
        public function splitComma(string $separator, string $pattern = '/[,，]/', int $limit = -1): static
        {
        }

        /**
         * split \/／.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::splitSlash()
         * @return static
         */
        public function splitSlash(string $separator, string $pattern = '#[\\/／]#', int $limit = -1): static
        {
        }

        /**
         * 分割字符串为层级数组.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::splitNested()
         * @return static
         */
        public function splitNested(string $string, string $firstPattern = '#[;；]#u', string $secondPattern = '#[\\/／]#u'): static
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::filterAvailable()
         * @return static
         */
        public function filterAvailable(): static
        {
        }

        /**
         * 按 GetKnightSortValue::getKSortValue() 降序排序.
         * @see \HughCube\Laravel\Knight\Mixin\Support\CollectionMixin::sortKnightModel()
         * @return static
         */
        public function sortKnightModel(): static
        {
        }
    }

    /**
     * @see \HughCube\Laravel\Knight\Mixin\Support\StrMixin
     */
    class Str
    {
        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Support\StrMixin::afterLast()
         * @return string
         */
        public function afterLast($subject, $search): string
        {
        }

        /**
         * 
         * @see \HughCube\Laravel\Knight\Mixin\Support\StrMixin::beforeLast()
         * @return string
         */
        public function beforeLast($subject, $search): string
        {
        }

        /**
         * @see \HughCube\Laravel\Knight\Mixin\Support\StrMixin::getMobilePattern()
         */
        public function getMobilePattern(): string
        {
        }
    }
}

namespace Illuminate\Console\Scheduling {

    /**
     * @see \HughCube\Laravel\Knight\Scheduling\Mixin\EventMixin
     */
    class Event
    {
        /**
         * 设置日志输出路径，通过回调函数动态生成路径.
         * @see \HughCube\Laravel\Knight\Scheduling\Mixin\EventMixin::sendOutputToDynamic()
         */
        public function sendOutputToDynamic(callable $callback, $append = true): mixed
        {
        }
    }
}
