<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/2/5
 * Time: 15:00.
 */

namespace HughCube\Laravel\Knight\Console\Commands;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * 随机增加 PostgreSQL 序列值以混淆业务量.
 *
 * 使用场景:
 *   - 不希望外部通过 ID 推算业务量
 *   - 定时任务随机增加序列值，使 ID 增长不可预测
 *
 * 并发安全:
 *   - 使用 pg_advisory_lock 确保同一时刻只有一个进程操作
 *   - 即使多个定时任务同时触发也不会有问题
 *
 * 使用示例:
 *   # 所有序列增加 100-1000 的随机值
 *   php artisan database:randomize-sequences
 *
 *   # 指定连接和增量范围
 *   php artisan database:randomize-sequences --connection=pgsql --min=500 --max=2000
 *
 *   # 只处理匹配的序列
 *   php artisan database:randomize-sequences --pattern="orders_*" --pattern="users_*"
 *
 *   # 排除某些序列
 *   php artisan database:randomize-sequences --exclude="*_log_*" --exclude="temp_*"
 *
 *   # 组合使用
 *   php artisan database:randomize-sequences --pattern="*_id_seq" --exclude="log_*" --min=100 --max=500
 */
class DatabaseRandomizeSequences extends \HughCube\Laravel\Knight\Console\Command
{
    /**
     * 咨询锁 ID，用于确保并发安全.
     */
    private const ADVISORY_LOCK_ID = 742198365;

    /**
     * @inheritdoc
     */
    protected $signature = 'database:randomize-sequences
                    {--connection= : 数据库连接名称}
                    {--min=100 : 随机增量最小值}
                    {--max=1000 : 随机增量最大值}
                    {--pattern=* : 序列名匹配模式(可多次指定)，支持通配符 *，不指定则匹配所有}
                    {--exclude=* : 序列名排除模式(可多次指定)，支持通配符 *，不指定则不排除}
                    {--dry-run : 仅显示将要操作的序列，不实际执行}
                    {--no-lock : 不使用咨询锁（不推荐，除非确定不会并发执行）}
                    {--force : 无需确认直接执行}
    ';

    /**
     * @inheritdoc
     */
    protected $description = 'Randomize PostgreSQL sequence values to obfuscate business volume.';

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        /** @var Connection $connection */
        $connection = DB::connection($this->option('connection') ?: null);
        $driver = $connection->getDriverName();

        // 检查是否为 PostgreSQL
        if ('pgsql' !== $driver) {
            $this->error(sprintf(
                'This command only supports PostgreSQL. Current driver: %s',
                $driver
            ));

            return 1;
        }

        $min = (int) $this->option('min');
        $max = (int) $this->option('max');

        // 验证参数
        if ($min < 1) {
            $this->error('Minimum increment must be at least 1.');

            return 1;
        }

        if ($max < $min) {
            $this->error('Maximum increment must be greater than or equal to minimum.');

            return 1;
        }

        // 获取所有序列
        $sequences = $this->getSequences($connection);

        if ($sequences->isEmpty()) {
            $this->info('No sequences found.');

            return 0;
        }

        // 应用过滤
        $sequences = $this->filterSequences($sequences);

        if ($sequences->isEmpty()) {
            $this->info('No sequences match the specified patterns.');

            return 0;
        }

        // 显示信息
        $this->info(sprintf('Found %d sequence(s) to randomize:', $sequences->count()));
        $this->newLine();

        foreach ($sequences as $seq) {
            $this->line(sprintf('  - %s', $seq));
        }

        $this->newLine();
        $this->info(sprintf('Increment range: %d ~ %d', $min, $max));

        // Dry run 模式
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Dry run mode - no changes made.');

            return 0;
        }

        // 确认执行
        if (! $this->option('force') && ! $this->confirm('Proceed with randomization?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // 执行随机化
        $useLock = ! $this->option('no-lock');

        if ($useLock) {
            return $this->executeWithLock($connection, $sequences, $min, $max);
        }

        return $this->executeRandomization($connection, $sequences, $min, $max);
    }

    /**
     * 获取所有序列.
     *
     * @param  Connection  $connection
     * @return Collection
     */
    protected function getSequences(Connection $connection): Collection
    {
        $rows = $connection->select("
            SELECT sequencename
            FROM pg_sequences
            WHERE schemaname = 'public'
            ORDER BY sequencename
        ");

        return Collection::wrap($rows)->pluck('sequencename');
    }

    /**
     * 根据 pattern 和 exclude 过滤序列.
     *
     * @param  Collection  $sequences
     * @return Collection
     */
    protected function filterSequences(Collection $sequences): Collection
    {
        $patterns = $this->option('pattern');
        $excludes = $this->option('exclude');

        // 标准化为数组
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        $excludes = is_array($excludes) ? $excludes : [$excludes];

        // 过滤空值
        $patterns = array_filter($patterns, function ($p) {
            return ! empty($p);
        });
        $excludes = array_filter($excludes, function ($e) {
            return ! empty($e);
        });

        return $sequences->filter(function ($seq) use ($patterns, $excludes) {
            // pattern 为空时匹配所有，否则检查是否匹配任一 pattern
            if (! empty($patterns) && ! $this->matchesAnyPattern($seq, $patterns)) {
                return false;
            }

            // exclude 为空时不排除，否则检查是否被排除
            if (! empty($excludes) && $this->matchesAnyPattern($seq, $excludes)) {
                return false;
            }

            return true;
        });
    }

    /**
     * 检查字符串是否匹配任一通配符模式.
     *
     * 使用 Laravel 的 Str::is() 方法
     * 支持通配符: * 匹配任意数量的任意字符
     *
     * @param  string  $string
     * @param  array  $patterns
     * @return bool
     */
    protected function matchesAnyPattern(string $string, array $patterns): bool
    {
        return Str::is($patterns, $string);
    }

    /**
     * 使用咨询锁执行随机化.
     *
     * @param  Connection  $connection
     * @param  Collection  $sequences
     * @param  int  $min
     * @param  int  $max
     * @return int
     */
    protected function executeWithLock(
        Connection $connection,
        Collection $sequences,
        int $min,
        int $max
    ): int {
        $this->info('Acquiring advisory lock...');

        // 尝试获取锁
        $result = $connection->selectOne(sprintf(
            'SELECT pg_try_advisory_lock(%d) as locked',
            self::ADVISORY_LOCK_ID
        ));

        if (! $result->locked) {
            $this->error('Failed to acquire lock. Another instance may be running.');

            return 1;
        }

        $this->info('Lock acquired.');

        try {
            return $this->executeRandomization($connection, $sequences, $min, $max);
        } finally {
            // 确保释放锁
            $connection->statement(sprintf(
                'SELECT pg_advisory_unlock(%d)',
                self::ADVISORY_LOCK_ID
            ));
            $this->info('Lock released.');
        }
    }

    /**
     * 执行序列随机化.
     *
     * @param  Connection  $connection
     * @param  Collection  $sequences
     * @param  int  $min
     * @param  int  $max
     * @return int
     */
    protected function executeRandomization(
        Connection $connection,
        Collection $sequences,
        int $min,
        int $max
    ): int {
        $this->newLine();
        $this->info('Randomizing sequences...');
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($sequences as $seq) {
            try {
                $increment = random_int($min, $max);
                $this->randomizeSequence($connection, $seq, $increment);
                $success++;
            } catch (Throwable $e) {
                $this->error(sprintf('  ✗ %s: %s', $seq, $e->getMessage()));
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Completed. Success: %d, Failed: %d',
            $success,
            $failed
        ));

        return $failed > 0 ? 1 : 0;
    }

    /**
     * 随机化单个序列.
     *
     * @param  Connection  $connection
     * @param  string  $sequenceName
     * @param  int  $increment
     * @return void
     */
    protected function randomizeSequence(
        Connection $connection,
        string $sequenceName,
        int $increment
    ): void {
        // 获取当前值
        $before = $connection->selectOne(sprintf(
            'SELECT last_value FROM "%s"',
            $sequenceName
        ))->last_value;

        // 增加序列值
        $connection->statement(sprintf(
            "SELECT setval('%s', nextval('%s') + %d)",
            $sequenceName,
            $sequenceName,
            $increment
        ));

        // 获取新值
        $after = $connection->selectOne(sprintf(
            'SELECT last_value FROM "%s"',
            $sequenceName
        ))->last_value;

        $this->info(sprintf(
            '  ✓ %s: %d → %d (+%d)',
            $sequenceName,
            $before,
            $after,
            $increment
        ));
    }
}
