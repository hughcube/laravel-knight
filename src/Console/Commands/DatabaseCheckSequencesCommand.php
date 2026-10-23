<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/3/13
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Console\Commands;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * 检查 PostgreSQL 表的主键最大值是否超过序列当前值.
 *
 * 使用场景:
 *   - 数据库迁移或导入后序列值未同步
 *   - 定期巡检确保序列值正确，避免主键冲突
 *
 * 使用示例:
 *   # 检查所有表
 *   php artisan database:check-sequences
 *
 *   # 检查并自动修复
 *   php artisan database:check-sequences --fix
 *
 *   # 修复时增加 100 的增长缓冲值
 *   php artisan database:check-sequences --fix --growth=100
 *
 *   # 检测到异常时发送 error 日志告警
 *   php artisan database:check-sequences --alert
 *
 *   # 指定连接
 *   php artisan database:check-sequences --connection=pgsql
 *
 *   # 只检查匹配的表
 *   php artisan database:check-sequences --pattern="orders" --pattern="users"
 *
 *   # 排除某些表
 *   php artisan database:check-sequences --exclude="log_*" --exclude="temp_*"
 */
class DatabaseCheckSequencesCommand extends \HughCube\Laravel\Knight\Console\Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'database:check-sequences
                    {--connection= : 数据库连接名称}
                    {--fix : 自动修复序列值(将序列设置为 max_id + growth)}
                    {--growth=10 : 修复时的增长缓冲值，防止并发写入导致冲突}
                    {--alert : 检测到异常时发送 error 级别日志告警}
                    {--pattern=* : 表名匹配模式(可多次指定)，支持通配符 *}
                    {--exclude=* : 表名排除模式(可多次指定)，支持通配符 *}
                    {--force : 修复时无需确认直接执行}
    ';

    /**
     * @inheritdoc
     */
    protected $description = 'Check if PostgreSQL table max IDs exceed their sequence values and optionally fix them.';

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        /** @var Connection $connection */
        $connection = DB::connection($this->option('connection') ?: null);
        $driver = $connection->getDriverName();

        if ('pgsql' !== $driver) {
            $this->error(sprintf(
                'This command only supports PostgreSQL. Current driver: %s',
                $driver
            ));

            return 1;
        }

        $tables = $this->getTables($connection);

        if ($tables->isEmpty()) {
            $this->info('No tables found.');

            return 0;
        }

        $tables = $this->filterTables($tables);

        if ($tables->isEmpty()) {
            $this->info('No tables match the specified patterns.');

            return 0;
        }

        $fix = $this->option('fix');
        $growth = max(0, intval($this->option('growth')));
        $alert = $this->option('alert');
        $force = $this->option('force');

        $this->info(sprintf('Checking %d table(s)...', $tables->count()));
        $this->newLine();

        $anomalies = [];
        $checked = 0;
        $skipped = 0;

        foreach ($tables as $table) {
            try {
                $result = $this->checkTable($connection, $table);

                if (null === $result) {
                    $skipped++;
                    continue;
                }

                $checked++;

                if ($result['is_anomaly']) {
                    $anomalies[] = $result;

                    $this->warn(sprintf(
                        '  ✗ %s.%s: max_id=%d, sequence_value=%d (差值: %d)',
                        $result['table'],
                        $result['primary_key'],
                        $result['max_id'],
                        $result['sequence_value'],
                        $result['max_id'] - $result['sequence_value']
                    ));
                } else {
                    $this->info(sprintf(
                        '  ✓ %s.%s: max_id=%d, sequence_value=%d',
                        $result['table'],
                        $result['primary_key'],
                        $result['max_id'],
                        $result['sequence_value']
                    ));
                }
            } catch (Throwable $e) {
                $this->error(sprintf('  ✗ %s: %s', $table, $e->getMessage()));
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Checked: %d, Anomalies: %d, Skipped: %d',
            $checked,
            count($anomalies),
            $skipped
        ));

        // 发送告警
        if ($alert && !empty($anomalies)) {
            $this->sendAlert($anomalies, $connection);
        }

        // 修复异常
        if ($fix && !empty($anomalies)) {
            $this->newLine();
            $this->fixAnomalies($connection, $anomalies, $growth, $force);
        }

        return empty($anomalies) ? 0 : 1;
    }

    /**
     * 获取所有表.
     *
     * @param Connection $connection
     *
     * @return Collection
     */
    protected function getTables(Connection $connection): Collection
    {
        $rows = $connection->select("
            SELECT tablename
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");

        return Collection::wrap($rows)->pluck('tablename');
    }

    /**
     * 根据 pattern 和 exclude 过滤表.
     *
     * @param Collection $tables
     *
     * @return Collection
     */
    protected function filterTables(Collection $tables): Collection
    {
        $patterns = $this->option('pattern');
        $excludes = $this->option('exclude');

        $patterns = is_array($patterns) ? $patterns : [$patterns];
        $excludes = is_array($excludes) ? $excludes : [$excludes];

        $patterns = array_filter($patterns, function ($p) {
            return !empty($p);
        });
        $excludes = array_filter($excludes, function ($e) {
            return !empty($e);
        });

        return $tables->filter(function ($table) use ($patterns, $excludes) {
            if (!empty($patterns) && !Str::is($patterns, $table)) {
                return false;
            }

            if (!empty($excludes) && Str::is($excludes, $table)) {
                return false;
            }

            return true;
        });
    }

    /**
     * 检查单个表的主键最大值与序列值.
     *
     * @param Connection $connection
     * @param string     $table
     *
     * @return array|null
     */
    protected function checkTable(Connection $connection, $table)
    {
        $primaryKey = $this->getPrimaryKeyColumn($connection, $table);

        if (null === $primaryKey) {
            $this->line(sprintf('  - %s: no primary key, skipped.', $table));

            return null;
        }

        $sequenceName = $this->getSequenceName($connection, $table, $primaryKey);

        if (null === $sequenceName) {
            $this->line(sprintf('  - %s: no sequence found for column "%s", skipped.', $table, $primaryKey));

            return null;
        }

        $maxId = $this->getMaxId($connection, $table, $primaryKey);
        $sequenceValue = $this->getSequenceCurrentValue($connection, $sequenceName);

        return [
            'table'          => $table,
            'primary_key'    => $primaryKey,
            'sequence_name'  => $sequenceName,
            'max_id'         => $maxId,
            'sequence_value' => $sequenceValue,
            'is_anomaly'     => $maxId > $sequenceValue,
        ];
    }

    /**
     * 获取表的主键列名.
     *
     * @param Connection $connection
     * @param string     $table
     *
     * @return string|null
     */
    protected function getPrimaryKeyColumn(Connection $connection, $table)
    {
        $rows = $connection->select('
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = ?::regclass AND i.indisprimary
        ', [$table]);

        if (!empty($rows)) {
            return $rows[0]->column_name;
        }

        return null;
    }

    /**
     * 获取序列名称.
     *
     * @param Connection $connection
     * @param string     $table
     * @param string     $column
     *
     * @return string|null
     */
    protected function getSequenceName(Connection $connection, $table, $column)
    {
        // Method 1: pg_get_serial_sequence
        $result = $connection->selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS sequence_name',
            [$table, $column]
        );

        if (!empty($result->sequence_name)) {
            return $result->sequence_name;
        }

        // Method 2: Parse from column default
        $result = $connection->selectOne('
            SELECT pg_get_expr(d.adbin, d.adrelid) AS column_default
            FROM pg_attrdef d
            JOIN pg_attribute a ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            WHERE a.attrelid = ?::regclass AND a.attname = ?
        ', [$table, $column]);

        if (!empty($result->column_default)) {
            if (preg_match("/nextval\\('([^']+)'(?:::regclass)?\\)/", $result->column_default, $matches)) {
                return $matches[1];
            }
        }

        // Method 3: Default naming convention
        $defaultSequence = sprintf('%s_%s_seq', $table, $column);
        $exists = $connection->selectOne("
            SELECT EXISTS (
                SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = ?
            ) AS exists
        ", [$defaultSequence]);

        if ($exists && $exists->exists) {
            return $defaultSequence;
        }

        // Method 4: Search via pg_depend
        $result = $connection->selectOne("
            SELECT s.relname AS sequence_name
            FROM pg_class s
            JOIN pg_depend d ON d.objid = s.oid
            JOIN pg_class t ON d.refobjid = t.oid
            JOIN pg_attribute a ON d.refobjid = a.attrelid AND d.refobjsubid = a.attnum
            WHERE s.relkind = 'S'
              AND t.relname = ?
              AND a.attname = ?
        ", [$table, $column]);

        if (!empty($result->sequence_name)) {
            return $result->sequence_name;
        }

        return null;
    }

    /**
     * 获取表中主键的最大值.
     *
     * @param Connection $connection
     * @param string     $table
     * @param string     $primaryKey
     *
     * @return int
     */
    protected function getMaxId(Connection $connection, $table, $primaryKey): int
    {
        $result = $connection->selectOne(sprintf(
            'SELECT COALESCE(MAX("%s"), 0) AS max_id FROM "%s"',
            $primaryKey,
            $table
        ));

        return intval($result->max_id);
    }

    /**
     * 获取序列当前值.
     *
     * @param Connection $connection
     * @param string     $sequenceName
     *
     * @return int
     */
    protected function getSequenceCurrentValue(Connection $connection, $sequenceName): int
    {
        // 处理 schema 限定的序列名，如 "public"."seq_name" 或 public.seq_name
        $cleanName = str_replace('"', '', $sequenceName);
        $parts = explode('.', $cleanName);

        if (count($parts) >= 2) {
            $quoted = sprintf('"%s"."%s"', $parts[0], $parts[1]);
        } else {
            $quoted = sprintf('"%s"', $parts[0]);
        }

        $result = $connection->selectOne(sprintf('SELECT last_value FROM %s', $quoted));

        return intval($result->last_value);
    }

    /**
     * 发送告警日志.
     *
     * @param array      $anomalies
     * @param Connection $connection
     *
     * @return void
     */
    protected function sendAlert(array $anomalies, Connection $connection): void
    {
        $details = array_map(function ($item) {
            return sprintf(
                '%s.%s: max_id=%d, sequence_value=%d',
                $item['table'],
                $item['primary_key'],
                $item['max_id'],
                $item['sequence_value']
            );
        }, $anomalies);

        $message = sprintf(
            'PostgreSQL sequence anomaly detected on connection "%s": %d table(s) affected. Details: %s',
            $connection->getName(),
            count($anomalies),
            implode('; ', $details)
        );

        Log::error($message);

        $this->warn('Alert sent via error log.');
    }

    /**
     * 修复异常的序列值.
     *
     * @param Connection $connection
     * @param array      $anomalies
     * @param int        $growth
     * @param bool       $force
     *
     * @return void
     */
    protected function fixAnomalies(Connection $connection, array $anomalies, int $growth, bool $force): void
    {
        $this->info(sprintf('Fixing %d anomaly(ies) with growth=%d...', count($anomalies), $growth));
        $this->newLine();

        if (!$force && !$this->confirm('Proceed with fixing sequences?')) {
            $this->info('Fix cancelled.');

            return;
        }

        foreach ($anomalies as $item) {
            try {
                // 重新获取当前最大值，防止在检查和修复之间有新数据写入
                $currentMaxId = $this->getMaxId($connection, $item['table'], $item['primary_key']);
                $newValue = $currentMaxId + $growth;

                $sequenceName = str_replace('"', '', $item['sequence_name']);
                $connection->statement(sprintf(
                    "SELECT setval('%s', %d)",
                    $sequenceName,
                    $newValue
                ));

                $this->info(sprintf(
                    '  ✓ %s: sequence "%s" set to %d (max_id=%d + growth=%d)',
                    $item['table'],
                    $sequenceName,
                    $newValue,
                    $currentMaxId,
                    $growth
                ));
            } catch (Throwable $e) {
                $this->error(sprintf(
                    '  ✗ %s: fix failed - %s',
                    $item['table'],
                    $e->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->info('Fix completed.');
    }
}
