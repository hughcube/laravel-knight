<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2025/5/12
 * Time: 23:04.
 */

namespace HughCube\Laravel\Knight\Console\Commands;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseResetAutoIncrementStartId extends \HughCube\Laravel\Knight\Console\Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'database:reset-auto-increment-start-id
                    {--connection= : Database connection name}
                    {--database= : Database name}
                    {--min=1 : Minimum starting value for auto-increment}
                    {--offset=0 : Additional offset to add on top of max ID}
                    {--table= : Specific table name (optional, process all tables if not specified)}
                    {--force : Run without confirmation}
    ';

    /**
     * @inheritdoc
     */
    protected $description = 'Reset the auto-increment start ID for tables (supports MySQL and PostgreSQL).';

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        /** @var Connection $connection */
        $connection = DB::connection($this->option('connection') ?: null);
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'])) {
            $this->error(sprintf('Unsupported database driver: %s. Only mysql and pgsql are supported.', $driver));

            return;
        }

        if ('mysql' === $driver && ! empty($database = $this->option('database'))) {
            $connection->getPdo()->exec(sprintf('use `%s`;', $database));
        }

        $tables = $this->getTables($connection, $driver);

        if ($tables->isEmpty()) {
            $this->info('No tables found.');

            return;
        }

        $specificTable = $this->option('table');
        if (! empty($specificTable)) {
            $tables = $tables->filter(function ($table) use ($specificTable) {
                return $table === $specificTable;
            });

            if ($tables->isEmpty()) {
                $this->error(sprintf('Table "%s" not found.', $specificTable));

                return;
            }
        }

        $min = intval($this->option('min'));
        $offset = intval($this->option('offset'));
        $force = $this->option('force');

        $this->info(sprintf('Database driver: %s', $driver));
        $this->info(sprintf('Min start value: %d, Offset: %d', $min, $offset));
        $this->newLine();

        foreach ($tables as $table) {
            $this->processTable($connection, $driver, $table, $min, $offset, $force);
        }

        $this->newLine();
        $this->info('Done.');
    }

    /**
     * @param  Connection  $connection
     * @param  string  $driver
     * @return Collection
     */
    protected function getTables(Connection $connection, $driver): Collection
    {
        if ('mysql' === $driver) {
            $rows = $connection->select('SHOW TABLES');

            return Collection::wrap($rows)->map(function ($row) {
                return Collection::wrap((array) $row)->first();
            });
        }

        // PostgreSQL
        $rows = $connection->select("
            SELECT tablename
            FROM pg_tables
            WHERE schemaname = 'public'
        ");

        return Collection::wrap($rows)->map(function ($row) {
            return $row->tablename;
        });
    }

    /**
     * @param  Connection  $connection
     * @param  string  $driver
     * @param  string  $table
     * @return string|null
     */
    protected function getPrimaryKeyColumn(Connection $connection, $driver, $table)
    {
        if ('mysql' === $driver) {
            $rows = $connection->select(sprintf("SHOW KEYS FROM `%s` WHERE Key_name = 'PRIMARY'", $table));
            if (! empty($rows)) {
                return $rows[0]->Column_name;
            }

            return null;
        }

        // PostgreSQL
        $rows = $connection->select('
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = ?::regclass AND i.indisprimary
        ', [$table]);

        if (! empty($rows)) {
            return $rows[0]->column_name;
        }

        return null;
    }

    /**
     * @param  Connection  $connection
     * @param  string  $driver
     * @param  string  $table
     * @param  string  $primaryKey
     * @return int
     */
    protected function getMaxId(Connection $connection, $driver, $table, $primaryKey): int
    {
        if ('mysql' === $driver) {
            $result = $connection->selectOne(sprintf('SELECT MAX(`%s`) AS max_id FROM `%s`', $primaryKey, $table));
        } else {
            $result = $connection->selectOne(sprintf('SELECT MAX("%s") AS max_id FROM "%s"', $primaryKey, $table));
        }

        return intval($result->max_id ?? 0);
    }

    /**
     * @param  Connection  $connection
     * @param  string  $driver
     * @param  string  $table
     * @param  int  $min
     * @param  int  $offset
     * @param  bool  $force
     * @return void
     */
    protected function processTable(Connection $connection, $driver, $table, $min, $offset, $force): void
    {
        $primaryKey = $this->getPrimaryKeyColumn($connection, $driver, $table);

        if (null === $primaryKey) {
            $this->warn(sprintf('Table "%s" has no primary key, skipping.', $table));

            return;
        }

        $maxId = $this->getMaxId($connection, $driver, $table, $primaryKey);
        $newStartId = max($min, $maxId + $offset);

        $message = sprintf(
            'Table "%s" (PK: %s): max_id=%d, new_start_id=%d',
            $table,
            $primaryKey,
            $maxId,
            $newStartId
        );

        if (! $force && true !== $this->confirm($message.' — Proceed?')) {
            $this->line(sprintf('Skipped: %s', $table));

            return;
        }

        $this->setAutoIncrement($connection, $driver, $table, $primaryKey, $newStartId);
        $this->info(sprintf('Updated: %s -> %d', $table, $newStartId));
    }

    /**
     * @param  Connection  $connection
     * @param  string  $driver
     * @param  string  $table
     * @param  string  $primaryKey
     * @param  int  $startId
     * @return void
     */
    protected function setAutoIncrement(Connection $connection, $driver, $table, $primaryKey, $startId): void
    {
        if ('mysql' === $driver) {
            $connection->statement(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = %d', $table, $startId));

            return;
        }

        // PostgreSQL - find and update the sequence
        $sequenceName = $this->getSequenceName($connection, $table, $primaryKey);

        if (null === $sequenceName) {
            $this->warn(sprintf('No sequence found for table "%s" column "%s"', $table, $primaryKey));

            return;
        }

        $connection->statement(sprintf("SELECT setval('%s', %d, false)", $sequenceName, $startId));
    }

    /**
     * @param  Connection  $connection
     * @param  string  $table
     * @param  string  $column
     * @return string|null
     */
    protected function getSequenceName(Connection $connection, $table, $column)
    {
        // Method 1: Use pg_get_serial_sequence (works for SERIAL/BIGSERIAL columns)
        $result = $connection->selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS sequence_name',
            [$table, $column]
        );

        if (! empty($result->sequence_name)) {
            return $result->sequence_name;
        }

        // Method 2: Parse sequence from column default value (e.g., nextval('sequence_name'::regclass))
        $result = $connection->selectOne('
            SELECT pg_get_expr(d.adbin, d.adrelid) AS column_default
            FROM pg_attrdef d
            JOIN pg_attribute a ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            WHERE a.attrelid = ?::regclass AND a.attname = ?
        ', [$table, $column]);

        if (! empty($result->column_default)) {
            // Extract sequence name from nextval('schema.sequence_name'::regclass) or nextval('sequence_name')
            if (preg_match("/nextval\\('([^']+)'(?:::regclass)?\\)/", $result->column_default, $matches)) {
                return $matches[1];
            }
        }

        // Method 3: Check if sequence exists with default naming convention
        $defaultSequence = sprintf('%s_%s_seq', $table, $column);
        $exists = $connection->selectOne("
            SELECT EXISTS (
                SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = ?
            ) AS exists
        ", [$defaultSequence]);

        if ($exists && $exists->exists) {
            return $defaultSequence;
        }

        // Method 4: Search for any sequence that might be associated with this table
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

        if (! empty($result->sequence_name)) {
            return $result->sequence_name;
        }

        return null;
    }
}
