<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/1/26
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MigrateRerun extends Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'migrate:krerun
                    {migrations?* : Migration file names or keywords to match (without .php extension) }
                    {--connection= : Database connection name }
                    {--path=database/migrations : Migration files path (supports absolute path) }
                    {--force : Force run all operations without confirmation (including down) }
                    {--skip-down : Skip down() rollback without asking }
    ';

    /**
     * @inheritdoc
     */
    protected $description = 'Delete migration records from migrations table and re-run the specified migrations.';

    /**
     * @var string
     */
    protected $migrationsTable = 'migrations';

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        $migrationKeywords = $this->argument('migrations');

        // 如果没有传入参数，交互式选择
        if (empty($migrationKeywords)) {
            $migrationKeywords = $this->askForMigrations();
            if (empty($migrationKeywords)) {
                $this->error('No migrations specified.');

                return 1;
            }
        }

        /** @var Connection $connection */
        $connection = DB::connection($this->option('connection') ?: null);
        $pathOption = $this->option('path');

        // 支持绝对路径和相对路径
        if ($this->isAbsolutePath($pathOption)) {
            $migrationPath = $pathOption;
        } else {
            $migrationPath = base_path($pathOption);
        }

        // 检查 migrations 目录是否存在
        if (! is_dir($migrationPath)) {
            $this->error(sprintf('Migration path does not exist: %s', $migrationPath));

            return 1;
        }

        // 获取所有 migration 文件
        $allMigrationFiles = $this->getMigrationFiles($migrationPath);

        if ($allMigrationFiles->isEmpty()) {
            $this->error('No migration files found.');

            return 1;
        }

        // 匹配要执行的 migration
        $matchedMigrations = $this->matchMigrations($allMigrationFiles, $migrationKeywords);

        if ($matchedMigrations->isEmpty()) {
            $this->error('No migrations matched the given keywords.');
            $this->line('');
            $this->line('Available migrations:');
            $allMigrationFiles->each(function ($file, $name) {
                $this->line(sprintf('  - %s', $name));
            });

            return 1;
        }

        // 显示匹配到的 migrations
        $this->info('Matched migrations:');
        $matchedMigrations->each(function ($file, $name) {
            $this->line(sprintf('  - %s', $name));
        });
        $this->line('');

        // 确认执行
        if (! $this->option('force') && ! $this->confirm('Do you want to re-run these migrations?', true)) {
            $this->info('Aborted.');

            return 0;
        }

        // 确保 migrations 表存在
        $this->ensureMigrationsTableExists($connection);

        // 按文件名排序执行
        $matchedMigrations = $matchedMigrations->sortKeys();

        $successCount = 0;
        $failCount = 0;

        foreach ($matchedMigrations as $migrationName => $migrationFile) {
            $this->line('');
            $this->info(sprintf('Processing: %s', $migrationName));

            try {
                $this->rerunMigration($connection, $migrationName, $migrationFile);
                $this->info(sprintf('  [OK] %s', $migrationName));
                $successCount++;
            } catch (Throwable $e) {
                $this->error(sprintf('  [FAIL] %s', $migrationName));
                $this->error(sprintf('  Error: %s', $e->getMessage()));
                $failCount++;

                if (! $this->option('force') && ! $this->confirm('Continue with remaining migrations?', false)) {
                    break;
                }
            }
        }

        $this->line('');
        $this->info(sprintf('Completed. Success: %d, Failed: %d', $successCount, $failCount));

        return $failCount > 0 ? 1 : 0;
    }

    /**
     * 交互式选择 migrations.
     *
     * @return array
     */
    protected function askForMigrations(): array
    {
        $input = $this->ask('Enter migration name(s) or keyword(s), separated by comma');

        if (empty($input)) {
            return [];
        }

        return array_map('trim', explode(',', $input));
    }

    /**
     * 获取所有 migration 文件.
     *
     * @param  string  $path
     * @return Collection
     */
    protected function getMigrationFiles(string $path): Collection
    {
        $files = Collection::make([]);

        foreach (glob($path.'/*.php') as $file) {
            $filename = basename($file, '.php');
            $files->put($filename, $file);
        }

        return $files->sortKeys();
    }

    /**
     * 匹配 migrations.
     *
     * @param  Collection  $allFiles
     * @param  array  $keywords
     * @return Collection
     */
    protected function matchMigrations(Collection $allFiles, array $keywords): Collection
    {
        $matched = Collection::make([]);

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            // 移除可能的 .php 后缀
            $keyword = preg_replace('/\.php$/i', '', $keyword);

            $allFiles->each(function ($file, $name) use ($keyword, $matched) {
                // 精确匹配或包含关键词
                if ($name === $keyword || Str::contains($name, $keyword)) {
                    $matched->put($name, $file);
                }
            });
        }

        return $matched;
    }

    /**
     * 确保 migrations 表存在.
     *
     * @param  Connection  $connection
     * @return void
     */
    protected function ensureMigrationsTableExists($connection): void
    {
        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable($this->migrationsTable)) {
            $this->warn('Migrations table does not exist, creating...');

            $schema->create($this->migrationsTable, function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });

            $this->info('Migrations table created.');
        }
    }

    /**
     * 重新执行单个 migration.
     *
     * @param  Connection  $connection
     * @param  string  $migrationName
     * @param  string  $migrationFile
     * @return void
     *
     * @throws Throwable
     */
    protected function rerunMigration($connection, string $migrationName, string $migrationFile): void
    {
        // 获取当前最大 batch
        $maxBatch = $connection->table($this->migrationsTable)->max('batch') ?: 0;
        $newBatch = $maxBatch + 1;

        // 解析 migration 实例
        $migrationInstance = $this->resolveMigrationInstance($migrationFile);

        if ($migrationInstance === null) {
            throw new \RuntimeException(sprintf('Could not resolve migration class from file: %s', $migrationFile));
        }

        // 获取 migration 指定的连接名
        if (method_exists($migrationInstance, 'getConnection') && $migrationInstance->getConnection() !== null) {
            $connectionName = $migrationInstance->getConnection();
        } else {
            $connectionName = $connection->getName();
        }

        // 检查 migrations 表中是否有记录
        $existingRecord = $connection->table($this->migrationsTable)
            ->where('migration', $migrationName)
            ->exists();

        // 使用事务执行
        $useTransaction = $this->shouldUseTransaction($migrationInstance);

        if ($useTransaction) {
            $connection->transaction(function () use ($connection, $migrationName, $migrationInstance, $newBatch, $connectionName, $existingRecord) {
                $this->executeMigration($connection, $migrationName, $migrationInstance, $newBatch, $connectionName, $existingRecord);
            });
        } else {
            $this->executeMigration($connection, $migrationName, $migrationInstance, $newBatch, $connectionName, $existingRecord);
        }
    }

    /**
     * 执行 migration.
     *
     * @param  Connection  $connection
     * @param  string  $migrationName
     * @param  object  $migrationInstance
     * @param  int  $batch
     * @param  string  $connectionName
     * @param  bool  $existingRecord
     * @return void
     */
    protected function executeMigration($connection, string $migrationName, $migrationInstance, int $batch, string $connectionName, bool $existingRecord): void
    {
        // 临时切换默认数据库连接
        /** @var \Illuminate\Database\DatabaseManager $db */
        $db = app('db');
        $previousConnection = $db->getDefaultConnection();
        $db->setDefaultConnection($connectionName);

        try {
            // 1. 只有当 migration 记录存在时，才考虑执行 down() 方法
            if ($existingRecord && method_exists($migrationInstance, 'down')) {
                $this->handleDownMethod($migrationInstance);
            }

            // 2. 删除 migrations 表中的记录
            $deleted = $connection->table($this->migrationsTable)
                ->where('migration', $migrationName)
                ->delete();

            if ($deleted > 0) {
                $this->line(sprintf('  Deleted %d record(s) from migrations table', $deleted));
            } else {
                $this->line('  No existing record in migrations table');
            }

            // 3. 执行 up 方法
            $this->line('  Running migration up()...');
            if (method_exists($migrationInstance, 'up')) {
                $migrationInstance->up();
            }
            $this->line('  Migration up() completed');

            // 4. 插入新记录到 migrations 表
            $connection->table($this->migrationsTable)->insert([
                'migration' => $migrationName,
                'batch' => $batch,
            ]);

            $this->line(sprintf('  Inserted record to migrations table (batch: %d)', $batch));
        } finally {
            $db->setDefaultConnection($previousConnection);
        }
    }

    /**
     * 处理 down() 方法的执行逻辑.
     *
     * @param  object  $migrationInstance
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function handleDownMethod($migrationInstance): void
    {
        // --skip-down 强制跳过
        if ($this->option('skip-down')) {
            $this->line('  Skipped down() rollback (--skip-down)');

            return;
        }

        // --force 强制执行
        $shouldRunDown = $this->option('force');

        // 默认交互式确认
        if (! $shouldRunDown) {
            $shouldRunDown = $this->confirm('  Run down() to rollback first? (DANGEROUS, may cause data loss)', false);
        }

        if (! $shouldRunDown) {
            $this->line('  Skipped down() rollback');

            return;
        }

        // 执行 down() 方法
        $this->line('  Running migration down() for rollback...');

        try {
            $migrationInstance->down();
            $this->line('  Rollback completed');
        } catch (Throwable $e) {
            $this->error(sprintf('  Error: down() failed: %s', $e->getMessage()));

            // 询问用户是否继续
            if ($this->option('force')) {
                $this->warn('  --force mode: continuing with up() despite down() failure');

                return;
            }

            if (! $this->confirm('  down() failed. Continue with up() anyway?', false)) {
                throw new \RuntimeException('Aborted: down() failed and user chose not to continue');
            }

            $this->warn('  Continuing with up() despite down() failure...');
        }
    }

    /**
     * 从文件解析 migration 实例.
     *
     * @param  string  $file
     * @return Migration|null
     */
    protected function resolveMigrationInstance(string $file)
    {
        // 记录已声明的类，用于检测新加载的类
        $classesBefore = get_declared_classes();

        // 读取文件内容
        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            return null;
        }

        // 检查是否是匿名类 (return new class extends Migration)
        $isAnonymousClass = preg_match('/return\s+new\s+class\s*(\([^)]*\))?\s*extends/', $fileContent);

        if ($isAnonymousClass) {
            // 匿名类：直接 require 获取返回值
            $instance = require $file;
            if ($instance instanceof Migration) {
                return $instance;
            }

            return null;
        }

        // 非匿名类：先加载文件，再实例化
        require_once $file;

        // 尝试从文件内容中解析类名
        if (preg_match('/class\s+(\w+)\s+extends/', $fileContent, $matches)) {
            $className = $matches[1];

            // 检查是否有命名空间
            if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $fileContent, $nsMatches)) {
                $fullClassName = $nsMatches[1].'\\'.$className;
                if (class_exists($fullClassName)) {
                    return new $fullClassName();
                }
            }

            // 没有命名空间的情况
            if (class_exists($className)) {
                return new $className();
            }
        }

        // 最后尝试遍历新加载的类
        $newClasses = array_diff(get_declared_classes(), $classesBefore);
        foreach ($newClasses as $class) {
            if (is_subclass_of($class, Migration::class)) {
                return new $class();
            }
        }

        return null;
    }

    /**
     * 判断是否应该使用事务
     *
     * @param  object  $migration
     * @return bool
     */
    protected function shouldUseTransaction($migration): bool
    {
        // 检查 migration 是否禁用了事务 (Laravel 8+)
        if (property_exists($migration, 'withinTransaction')) {
            /** @var bool $withinTransaction */
            $withinTransaction = $migration->withinTransaction;

            return $withinTransaction;
        }

        return true;
    }

    /**
     * 判断路径是否为绝对路径.
     *
     * @param  string  $path
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        // Unix 绝对路径
        if (strpos($path, '/') === 0) {
            return true;
        }

        // Windows 绝对路径 (C:\, D:\, etc.)
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return true;
        }

        return false;
    }
}
