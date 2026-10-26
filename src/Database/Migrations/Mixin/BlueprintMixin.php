<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/1/20
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Database\Migrations\Mixin;

use Closure;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migration 专用的 Blueprint 扩展 Mixin.
 *
 * 为 Laravel Schema Blueprint 提供额外的字段和索引方法扩展。
 * 此 Mixin 专门用于 Migration 场景，在 Migration 基类中自动注册。
 * 所有方法使用 knight 前缀以避免与框架冲突。
 *
 * @mixin-target \Illuminate\Database\Schema\Blueprint
 */
class BlueprintMixin
{
    /**
     * 添加 Knight 常用字段(反顺序).
     *
     * 字段顺序: created_at, updated_at, deleted_at, ukey, data_version
     *
     * 示例:
     *   Schema::create('users', function (Blueprint $table) {
     *       $table->id();
     *       $table->string('name');
     *       $table->knightColumnsReversed();
     *   });
     *
     * @return Closure
     */
    public function knightColumnsReversed(): Closure
    {
        return function () {
            /** @var Blueprint $this */
            $this->timestamp('created_at')->nullable()->default(null)->comment('Record creation timestamp');
            $this->timestamp('updated_at')->nullable()->default(null)->comment('Record last update timestamp');
            $this->timestamp('deleted_at')->nullable()->default(null)->comment('Soft delete timestamp');
            $this->bigInteger('data_version')->default(0)->comment('Data version for optimistic locking');
            $this->string('ukey', 255)->nullable()->default('')->comment('Unique key for composite unique index');
            $this->jsonb('options')->default('{}')->comment('Extra options');
            $this->bigInteger('sort')->default(0)->comment('Sorting weight');

            return $this;
        };
    }

    /**
     * 添加 Knight 常用字段.
     *
     * 字段顺序: ukey, data_version, created_at, updated_at, deleted_at
     *
     * 示例:
     *   Schema::create('users', function (Blueprint $table) {
     *       $table->id();
     *       $table->string('name');
     *       $table->knightColumns();
     *   });
     *
     * @return Closure
     */
    public function knightColumns(): Closure
    {
        return function () {
            /** @var Blueprint $this */
            $this->bigInteger('sort')->default(0)->comment('Sorting weight');
            $this->jsonb('options')->default('{}')->comment('Extra options');
            $this->string('ukey', 255)->nullable()->default('')->comment('Unique key for composite unique index');
            $this->bigInteger('data_version')->default(0)->comment('Data version for optimistic locking');
            $this->timestamp('created_at')->nullable()->default(null)->comment('Record creation timestamp');
            $this->timestamp('updated_at')->nullable()->default(null)->comment('Record last update timestamp');
            $this->timestamp('deleted_at')->nullable()->default(null)->comment('Soft delete timestamp');

            return $this;
        };
    }

    /**
     * 创建 GIN 索引 (PostgreSQL).
     *
     * 注意: 多列联合索引需要先安装 btree_gin 扩展: CREATE EXTENSION btree_gin;
     *
     * 示例:
     *   $table->knightGin('tags');
     *   $table->knightGin(['tenant_id', 'tags']);
     *   $table->knightGin('metadata', 'custom_index_name');
     *
     * @return Closure(string|array $columns, string|null $indexName = null): static
     */
    public function knightGin(): Closure
    {
        return function ($columns, ?string $indexName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $columns = is_array($columns) ? $columns : [$columns];
            $columnSuffix = implode('_', $columns);
            $indexName = $indexName ?? "idx_{$tableName}_{$columnSuffix}_gin";

            $this->addCommand('knightGinIndex', ['columns' => $columns, 'indexName' => $indexName]);

            return $this;
        };
    }

    /**
     * 创建条件 GIN 索引 (PostgreSQL).
     *
     * 注意: 多列联合索引需要先安装 btree_gin 扩展: CREATE EXTENSION btree_gin;
     *
     * 示例:
     *   $table->knightGinWhere('tags', 'deleted_at IS NULL');
     *   $table->knightGinWhere(['tenant_id', 'tags'], 'deleted_at IS NULL');
     *
     * @return Closure(string|array $columns, string $whereCondition, string|null $indexName = null): static
     */
    public function knightGinWhere(): Closure
    {
        return function ($columns, string $whereCondition, ?string $indexName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $columns = is_array($columns) ? $columns : [$columns];
            $columnSuffix = implode('_', $columns);
            $indexName = $indexName ?? "idx_{$tableName}_{$columnSuffix}_gin";

            $this->addCommand('knightGinIndexWhere', [
                'columns' => $columns,
                'where' => $whereCondition,
                'indexName' => $indexName,
            ]);

            return $this;
        };
    }

    /**
     * 创建条件 GIN 索引，仅对未软删除的记录生效 (PostgreSQL).
     *
     * 注意: 多列联合索引需要先安装 btree_gin 扩展: CREATE EXTENSION btree_gin;
     *
     * 示例:
     *   $table->knightGinWhereNotDeleted('tags');
     *   $table->knightGinWhereNotDeleted(['tenant_id', 'tags']);
     *
     * @return Closure
     */
    public function knightGinWhereNotDeleted(): Closure
    {
        return function ($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at') {
            /** @var Blueprint $this */
            /** @phpstan-ignore-next-line */
            return $this->knightGinWhere($columns, "{$deletedAtColumn} IS NULL", $indexName);
        };
    }

    /**
     * 创建条件唯一索引 (PostgreSQL).
     *
     * 示例:
     *   $table->knightUniqueWhere('email', 'deleted_at IS NULL');
     *   $table->knightUniqueWhere(['tenant_id', 'email'], 'deleted_at IS NULL');
     *
     * @return Closure(string|array $columns, string $whereCondition, string|null $indexName = null): static
     */
    public function knightUniqueWhere(): Closure
    {
        return function ($columns, string $whereCondition, ?string $indexName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $columns = is_array($columns) ? $columns : [$columns];
            $columnSuffix = implode('_', $columns);
            $indexName = $indexName ?? "uk_{$tableName}_{$columnSuffix}";

            $this->addCommand('knightUniqueWhere', [
                'columns' => $columns,
                'where' => $whereCondition,
                'indexName' => $indexName,
            ]);

            return $this;
        };
    }

    /**
     * 创建条件索引 (PostgreSQL).
     *
     * 示例:
     *   $table->knightIndexWhere('status', "status = 'active'");
     *   $table->knightIndexWhere(['user_id', 'status'], 'deleted_at IS NULL');
     *
     * @return Closure
     */
    public function knightIndexWhere(): Closure
    {
        return function ($columns, string $whereCondition, ?string $indexName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $columns = is_array($columns) ? $columns : [$columns];
            $columnSuffix = implode('_', $columns);
            $indexName = $indexName ?? "idx_{$tableName}_{$columnSuffix}";

            $this->addCommand('knightIndexWhere', [
                'columns' => $columns,
                'where' => $whereCondition,
                'indexName' => $indexName,
            ]);

            return $this;
        };
    }

    /**
     * 创建条件唯一索引，仅对未软删除的记录生效 (PostgreSQL).
     *
     * 示例:
     *   $table->knightUniqueWhereNotDeleted('email');
     *   $table->knightUniqueWhereNotDeleted(['tenant_id', 'email']);
     *
     * @return Closure
     */
    public function knightUniqueWhereNotDeleted(): Closure
    {
        return function ($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at') {
            /** @var Blueprint $this */
            /** @phpstan-ignore-next-line */
            return $this->knightUniqueWhere($columns, "{$deletedAtColumn} IS NULL", $indexName);
        };
    }

    /**
     * 创建条件索引，仅对未软删除的记录生效 (PostgreSQL).
     *
     * 示例:
     *   $table->knightIndexWhereNotDeleted('user_id');
     *   $table->knightIndexWhereNotDeleted(['user_id', 'status']);
     *
     * @return Closure
     */
    public function knightIndexWhereNotDeleted(): Closure
    {
        return function ($columns, ?string $indexName = null, string $deletedAtColumn = 'deleted_at') {
            /** @var Blueprint $this */
            /** @phpstan-ignore-next-line */
            return $this->knightIndexWhere($columns, "{$deletedAtColumn} IS NULL", $indexName);
        };
    }

    // ==================== PostgreSQL Array Column Methods ====================

    /**
     * 添加 INTEGER[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightIntArray('scores');
     *   $table->knightIntArray('scores')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightIntArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightIntArray', $column);
        };
    }

    /**
     * 添加 BIGINT[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightBigIntArray('user_ids');
     *   $table->knightBigIntArray('user_ids')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightBigIntArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightBigIntArray', $column);
        };
    }

    /**
     * 添加 SMALLINT[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightSmallIntArray('ratings');
     *   $table->knightSmallIntArray('ratings')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightSmallIntArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightSmallIntArray', $column);
        };
    }

    /**
     * 添加 TEXT[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightTextArray('tags');
     *   $table->knightTextArray('tags')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightTextArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightTextArray', $column);
        };
    }

    /**
     * 添加 VARCHAR(n)[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightVarcharArray('codes', 50);
     *   $table->knightVarcharArray('codes', 50)->nullable();
     *
     * @return Closure(string $column, int $length = 255): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightVarcharArray(): Closure
    {
        return function (string $column, int $length = 255) {
            /** @var Blueprint $this */
            return $this->addColumn('knightVarcharArray', $column, ['length' => $length]);
        };
    }

    /**
     * 添加 BOOLEAN[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightBooleanArray('flags');
     *   $table->knightBooleanArray('flags')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightBooleanArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightBooleanArray', $column);
        };
    }

    /**
     * 添加 DOUBLE PRECISION[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightDoubleArray('prices');
     *   $table->knightDoubleArray('prices')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightDoubleArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightDoubleArray', $column);
        };
    }

    /**
     * 添加 REAL[] (单精度浮点) 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightFloatArray('coordinates');
     *   $table->knightFloatArray('coordinates')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightFloatArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightFloatArray', $column);
        };
    }

    /**
     * 添加 UUID[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightUuidArray('related_ids');
     *   $table->knightUuidArray('related_ids')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightUuidArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightUuidArray', $column);
        };
    }

    /**
     * 添加 NUMERIC[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightNumericArray('amounts');
     *   $table->knightNumericArray('amounts', 10, 2)->nullable();
     *
     * @return Closure
     */
    public function knightNumericArray(): Closure
    {
        return function (string $column, ?int $precision = null, ?int $scale = null) {
            /** @var Blueprint $this */
            return $this->addColumn('knightNumericArray', $column, [
                'precision' => $precision,
                'scale' => $scale,
            ]);
        };
    }

    /**
     * 添加 TIMESTAMPTZ[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightTimestamptzArray('event_times');
     *   $table->knightTimestamptzArray('event_times')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightTimestamptzArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightTimestamptzArray', $column);
        };
    }

    /**
     * 添加 DATE[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightDateArray('holidays');
     *   $table->knightDateArray('holidays')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightDateArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightDateArray', $column);
        };
    }

    /**
     * 添加 JSONB[] 数组列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightJsonbArray('metadata_list');
     *   $table->knightJsonbArray('metadata_list')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightJsonbArray(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightJsonbArray', $column);
        };
    }

    // ==================== PostgreSQL Full-Text Search Column Methods ====================

    /**
     * 添加 TSVECTOR 列 (PostgreSQL).
     *
     * 用于存储全文搜索向量。通常与 GIN 索引配合使用。
     *
     * 示例:
     *   $table->knightTsVector('search_vector');
     *   $table->knightTsVector('search_vector')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightTsVector(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightTsVector', $column);
        };
    }

    /**
     * 添加 TSQUERY 列 (PostgreSQL).
     *
     * 用于存储全文搜索查询表达式。
     *
     * 示例:
     *   $table->knightTsQuery('saved_query');
     *   $table->knightTsQuery('saved_query')->nullable();
     *
     * @return Closure(string $column): \Illuminate\Database\Schema\ColumnDefinition
     */
    public function knightTsQuery(): Closure
    {
        return function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('knightTsQuery', $column);
        };
    }

    // ==================== PostgreSQL Sequence Methods ====================

    /**
     * 设置序列的下一个值 (PostgreSQL).
     *
     * 用于在 Migration 中指定主键自增 ID 的起始值。
     * 下一次调用 nextval() 将返回指定的值。
     *
     * 示例:
     *   // 设置 id 列的序列从 1000 开始
     *   $table->knightSetSequenceValue('id', 1000);
     *
     *   // 使用自定义序列名
     *   $table->knightSetSequenceValue('id', 1000, 'my_custom_seq');
     *
     * @return Closure(string $column, int $value, string|null $sequenceName = null): static
     */
    public function knightSetSequenceValue(): Closure
    {
        return function (string $column, int $value, ?string $sequenceName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $sequenceName = $sequenceName ?? "{$tableName}_{$column}_seq";

            $this->addCommand('knightSetSequenceValue', [
                'column' => $column,
                'value' => $value,
                'sequenceName' => $sequenceName,
            ]);

            return $this;
        };
    }

    /**
     * 重启序列 (PostgreSQL).
     *
     * 使用 ALTER SEQUENCE ... RESTART WITH 语法重启序列。
     * 下一次调用 nextval() 将返回指定的值。
     *
     * 示例:
     *   // 重启 id 列的序列从 1000 开始
     *   $table->knightRestartSequence('id', 1000);
     *
     *   // 使用自定义序列名
     *   $table->knightRestartSequence('id', 1000, 'my_custom_seq');
     *
     * @return Closure(string $column, int $value, string|null $sequenceName = null): static
     */
    public function knightRestartSequence(): Closure
    {
        return function (string $column, int $value, ?string $sequenceName = null) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            $sequenceName = $sequenceName ?? "{$tableName}_{$column}_seq";

            $this->addCommand('knightRestartSequence', [
                'column' => $column,
                'value' => $value,
                'sequenceName' => $sequenceName,
            ]);

            return $this;
        };
    }

    /**
     * 创建一个独立的序列 (PostgreSQL).
     *
     * 创建一个可以被多个表共享使用的序列。
     *
     * 示例:
     *   // 创建一个全局序列，从 1 开始
     *   $table->knightCreateSequence('global_id_seq');
     *
     *   // 创建一个从 1000 开始的序列
     *   $table->knightCreateSequence('global_id_seq', 1000);
     *
     *   // 创建一个带完整配置的序列（含缓存）
     *   $table->knightCreateSequence('global_id_seq', 1000, 1, null, 1, false, 20);
     *
     * 参数说明:
     *   - sequenceName: 序列名称，建议使用 _seq 后缀，如 'global_id_seq'
     *   - startWith:    起始值，首次 nextval() 返回此值，默认 1
     *   - incrementBy:  步频/增量，每次 nextval() 递增的值，默认 1
     *                   设为 2 则序列为 1,3,5,7...；设为 -1 可实现递减序列
     *   - maxValue:     最大值，null 表示无上限 (BIGINT 最大值 9223372036854775807)
     *                   达到最大值后的行为取决于 cycle 参数
     *   - minValue:     最小值，默认 1，递减序列时可能需要设置负数
     *   - cycle:        是否循环，默认 false
     *                   true: 达到 maxValue 后回到 minValue 继续
     *                   false: 达到 maxValue 后抛出错误
     *   - cache:        缓存数量，默认 1（不缓存）
     *                   每个数据库会话独立预分配 N 个值到内存
     *
     * CACHE 参数详解:
     *   - cache=1 (默认): 不缓存，每次 nextval 都访问序列对象，保证严格顺序
     *   - cache=N (N>1): 每个会话预分配 N 个序列值到内存，提升高并发性能
     *
     * CACHE 风险警告:
     *   1. 序列值空洞: 数据库崩溃/连接断开时，预分配但未使用的值会丢失
     *   2. 顺序不保证: 多会话并发时，ID 可能不按插入顺序递增
     *      例: 会话A获得1-10，会话B获得11-20，实际插入可能是 1,11,2,12...
     *   3. 回滚不归还: 事务回滚后，已获取的序列值不会归还
     *   4. 共享序列放大: 多表共享序列时，空洞问题会更明显
     *
     * 建议:
     *   - 对 ID 连续性有严格要求的场景，使用 cache=1
     *   - 高并发写入且允许空洞的场景，可设置 cache=20~100
     *
     * @return Closure
     */
    public function knightCreateSequence(): Closure
    {
        return function (
            string $sequenceName,
            int $startWith = 1,
            int $incrementBy = 1,
            ?int $maxValue = null,
            int $minValue = 1,
            bool $cycle = false,
            int $cache = 1
        ) {
            /** @var Blueprint $this */
            $this->addCommand('knightCreateSequence', [
                'sequenceName' => $sequenceName,    // 序列名称，全局唯一标识符
                'startWith' => $startWith,          // 起始值，首次 nextval() 返回此值
                'incrementBy' => $incrementBy,      // 步频，每次递增的值（可为负数实现递减）
                'maxValue' => $maxValue,            // 最大值，null=无限制，达到后根据 cycle 决定行为
                'minValue' => $minValue,            // 最小值，递减序列或 cycle=true 时的下限
                'cycle' => $cycle,                  // 是否循环，true=达到边界后循环，false=达到边界后报错
                'cache' => max(1, $cache),    // 缓存数量，每会话预分配值数量，>=1
            ]);

            return $this;
        };
    }

    /**
     * 删除序列 (PostgreSQL).
     *
     * 示例:
     *   $table->knightDropSequence('global_id_seq');
     *   $table->knightDropSequence('global_id_seq', true); // IF EXISTS
     *
     * @return Closure(string $sequenceName, bool $ifExists = false): static
     */
    public function knightDropSequence(): Closure
    {
        return function (string $sequenceName, bool $ifExists = false) {
            /** @var Blueprint $this */
            $this->addCommand('knightDropSequence', [
                'sequenceName' => $sequenceName,
                'ifExists' => $ifExists,
            ]);

            return $this;
        };
    }

    /**
     * 添加使用指定序列的 BIGINT 主键列 (PostgreSQL).
     *
     * 此方法创建一个 BIGINT 列，其默认值为从指定序列获取的下一个值。
     * 这允许多个表共享同一个序列，确保跨表的 ID 唯一性。
     *
     * 示例:
     *   // 创建使用全局序列的主键
     *   $table->knightIdWithSequence('id', 'global_id_seq');
     *
     *   // 创建使用自定义序列的非主键列
     *   $table->knightIdWithSequence('external_id', 'external_id_seq', false);
     *
     * @return Closure
     */
    public function knightIdWithSequence(): Closure
    {
        return function (string $column, string $sequenceName, bool $primary = true) {
            /** @var Blueprint $this */
            $this->addCommand('knightIdWithSequence', [
                'column' => $column,
                'sequenceName' => $sequenceName,
                'primary' => $primary,
            ]);

            return $this;
        };
    }

    /**
     * 修改列以使用指定的序列 (PostgreSQL).
     *
     * 将现有列的默认值改为从指定序列获取。
     *
     * 示例:
     *   // 修改 id 列使用全局序列
     *   $table->knightUseSequence('id', 'global_id_seq');
     *
     * @return Closure
     */
    public function knightUseSequence(): Closure
    {
        return function (string $column, string $sequenceName) {
            /** @var Blueprint $this */
            $this->addCommand('knightUseSequence', [
                'column' => $column,
                'sequenceName' => $sequenceName,
            ]);

            return $this;
        };
    }
}
