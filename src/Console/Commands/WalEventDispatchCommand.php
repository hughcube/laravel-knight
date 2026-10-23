<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Contracts\Database\HasWalHandler;
use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;
use HughCube\Laravel\Knight\Jobs\WalChangesDispatchJob;
use Illuminate\Database\Connection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

/**
 * 监听 PostgreSQL WAL (Write-Ahead Log) 变更并分发事件。
 *
 * ## 原理
 *
 * PostgreSQL 的逻辑复制机制通过 WAL 记录所有数据变更。本命令利用 wal2json 插件
 * 将 WAL 二进制数据解码为 JSON，从中提取表名、操作类型（INSERT/UPDATE/DELETE）、
 * 新旧列值等信息，封装为 WalChangeRecord 值对象，通过 WalChangesDispatchJob 以
 * 字符串事件（wal:{table}:{kind}）分发给应用层。
 *
 * ## 核心流程
 *
 * 1. **初始化**：创建/检查复制槽 → 扫描实现 HasWalHandler 的 Model → 构建分区表映射
 * 2. **主循环**：轮询 WAL 变更 → 解析 wal2json JSON → 构建 WalChangeRecord → 分发事件
 * 3. **终止**：内存超限 / 连续错误达上限 / 收到 SIGTERM|SIGINT 信号
 *
 * ## 消费模式 (--mode)
 *
 * - **advance** (默认，推荐)：peek_changes 读取 → 分发事件 → 手动 advance 推进槽位。
 *   事件分发失败不会丢失 WAL 数据，下次轮询可重试；无变更时还会推进到当前 WAL LSN，
 *   消除共享实例上的假性滞后。
 * - **auto**：成功分发后即消费。为避免 get_changes 与服务端游标组合在大事务场景下不稳定，
 *   流式读取时内部实现为 peek_changes + 成功后 advance；对调用方仍表现为“成功消费后下一次读不到”。
 * - **peek**：只读不消费，用于调试。每次轮询都能看到相同数据。
 *
 * ## 事件分发 (--queue-connection / --queue)
 *
 * 每条 WAL 变更通过 WalChangesDispatchJob 分发字符串事件 wal:{table}:{kind}，
 * 应用层通过 Event::listen() 订阅事件并自行处理（缓存清理、同步等）。
 * Job 本身不做任何业务处理，仅负责事件转发。
 *
 * 通过 --queue-connection 和 --queue 控制 Job 的执行方式：
 * - 默认（sync）：在本命令进程内同步执行
 * - --queue-connection=redis --queue=wal-events：推入 redis 的 wal-events 队列，适合生产
 *
 * 注意：每条 WAL 变更产生一个独立的 Job。高吞吐场景（如批量 INSERT）会产生等量的
 * Job dispatch，生产环境建议使用 queue 模式异步处理。
 *
 * ## 分区表支持
 *
 * 启动时通过 pg_inherits 系统表自动构建 子表→父表 映射。WAL 中记录的是分区子表名
 * （如 orders_p0），命令自动解析为父表名（orders）查找对应的 Handler。
 * WalChangeRecord 同时保留原始子表名（partitionTable）和解析后的父表名（table）。
 *
 * ## 防幻影滞后 (Phantom Lag Prevention)
 *
 * 共享 PG 实例中，其他数据库的 WAL 活动会推进全局 LSN，但本数据库的复制槽不动，
 * 导致监控指标显示持续增长的"假性滞后"。advance 模式下，每次 peek 前记录当前 LSN，
 * 若 peek 结果为空则 advance 到该 LSN，消除假性滞后。
 *
 * ## wal2json format-version 2（默认）
 *
 * 默认使用 format-version 2，每行变更输出一个独立的 JSON 对象：
 * - 大事务不会产生巨型单条 record（v1 的 OOM 根因）
 * - 每条 change 独立可解析，内存消耗恒定
 * - 使用 --format-version=1 切换回 v1 格式（不推荐）
 *
 * ## 自动表过滤 (add-tables)
 *
 * 默认自动启用：根据 discoverWalHandlers() 发现的被监听表名，
 * 自动构建 wal2json 的 add-tables 参数（白名单），只输出被监听表的变更。
 * 未被监听的表（如 telescope_entries）在 wal2json 插件层直接过滤，不传输到应用层。
 * 使用 --no-add-tables 禁用此行为。
 *
 * ## wal2json 参数透传 (--wal2json-params)
 *
 * 支持向 wal2json 插件传递任意参数（key=value 格式），常用于控制输出列：
 * - filter-columns=content,body  排除大字段，降低内存消耗
 * - add-columns=id,status        只包含指定列
 *
 * ## 错误恢复
 *
 * 轮询异常时采用指数退避：1s → 2s → 4s → ... → 60s（上限），自动重连数据库并
 * 重新检查复制槽。连续错误达到 --max-errors 上限时自动停止。
 *
 * ## 使用示例
 *
 * ```bash
 * # 基本用法（默认 advance 模式 + sync 同步执行）
 * php artisan wal:event-dispatch
 *
 * # 生产环境推荐：推入 redis 队列
 * php artisan wal:event-dispatch --queue-connection=redis --queue=wal-events
 *
 * # 过滤大字段，降低内存消耗
 * php artisan wal:event-dispatch --wal2json-params="filter-columns=content,raw_body"
 *
 * # 自定义复制槽 + 更快的轮询间隔
 * php artisan wal:event-dispatch --slot=my_app_wal --interval=0.5 --batch=2000
 *
 * # 调试模式：只读不消费
 * php artisan wal:event-dispatch --mode=peek
 *
 * # 禁用自动 add-tables（接收所有表的 WAL 变更）
 * php artisan wal:event-dispatch --no-add-tables
 *
 * # 使用 v1 格式（不推荐，大事务可能 OOM）
 * php artisan wal:event-dispatch --format-version=1
 *
 * # 自定义 Model 扫描路径
 * php artisan wal:event-dispatch --model-path="app/Models:App\\Models" --model-path="app/Domain:App\\Domain"
 * ```
 *
 * ## 应用层接入
 *
 * 1. Model 实现 HasWalHandler 接口（getTable + onKnightModelChanged）
 * 2. 注册字符串事件 Listener（如 Event::listen('wal:questions:update', MyListener::class)）
 * 3. Listener 通过 WalChangeRecord::toModel() 获取填充好的 Model 实例
 *
 * 注意：缓存清理等业务逻辑需应用层自行在 Listener 中处理，Job 仅负责事件分发。
 *
 * @see WalChangeRecord      WAL 变更记录值对象，包含 toModel() 方法
 * @see WalChangesDispatchJob 事件分发 Job（sync/queue 统一入口）
 * @see HasWalHandler         Model 需实现的接口
 */
class WalEventDispatchCommand extends Command
{
    const MODE_AUTO = 'auto';
    const MODE_ADVANCE = 'advance';
    const MODE_PEEK = 'peek';

    protected $signature = 'wal:event-dispatch
        {--connection= : Database connection name}
        {--slot= : Replication slot name, default: APP_NAME_APP_ENV_wal_event}
        {--interval=0.5 : Poll interval in seconds, supports decimals}
        {--batch=1000 : Max number of changes per poll}
        {--memory=128 : Memory limit in MB, process stops when exceeded}
        {--max-errors=30 : Max consecutive errors before process exits, 0 means unlimited}
        {--mode=advance : Consume mode: auto(consume on successful dispatch), advance(peek+advance after dispatch, default), peek(read-only for debugging)}
        {--model-path=* : Model scanning paths, can be specified multiple times (default: app/Models)}
        {--wal2json-params=* : wal2json plugin parameters, e.g. --wal2json-params="filter-columns=content,body"}
        {--no-add-tables : Disable auto add-tables parameter (by default, only monitored tables are included in wal2json output)}
        {--filter-tables=* : Tables to exclude from wal2json output (blacklist), e.g. --filter-tables=telescope_entries --filter-tables=telescope_entries_tags}
        {--format-version=2 : wal2json format version (1 or 2). Version 2 outputs one row per change, preventing OOM on large transactions}
        {--queue= : Queue name for WalChangesDispatchJob}
        {--queue-connection=sync : Queue connection for WalChangesDispatchJob, e.g. sync, redis}
        {--partition-refresh=0 : Partition map refresh interval in seconds, 0 means no auto-refresh (default)}
        {--slot-lag-warning=0 : Slot lag warning threshold in MB, 0 means no check (default). Process logs warning when exceeded}
        {--slot-lag-critical=0 : Slot lag critical threshold in MB, 0 means no check (default). Process stops when exceeded}';

    protected $description = 'Monitor PostgreSQL WAL changes and dispatch corresponding events';

    /**
     * @var bool
     */
    protected $shouldRun = true;

    /**
     * @var int Consecutive error count for exponential backoff.
     */
    protected $errorStreak = 0;

    /**
     * 自动生成的 add-tables 参数值，由 handle() 初始化。
     * 格式为 wal2json 要求的逗号分隔表名（如 "public.users,public.orders"）。
     * null 表示不自动添加（用户通过 --no-add-tables 禁用，或无被监听表）。
     *
     * @var string|null
     */
    protected $autoAddTables;

    /**
     * 分区映射最后刷新的时间戳（Unix timestamp）。
     * 用于主循环中定期刷新分区映射，确保新增分区能被自动发现。
     *
     * @var int
     */
    protected $partitionMapRefreshedAt = 0;

    /**
     * 缓存的消费模式，运行时不变。
     *
     * @var string|null
     */
    protected $cachedMode;

    /**
     * 缓存的 wal2json 参数（占位符 + 绑定值），分区刷新时失效。
     *
     * @var array|null [string, array]
     */
    protected $cachedWal2jsonParams;

    /**
     * Telescope 是否可用的缓存标记。
     *
     * @var bool|null
     */
    protected $telescopeAvailable;

    /**
     * 主入口：初始化 → 主循环 → 清理退出。
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $slot = $this->getSlotName();
        $interval = max(0.01, floatval($this->option('interval')));
        $batch = max(1, intval($this->option('batch')));

        $this->ensureSlotExists($slot);
        $this->registerSignalHandlers();
        $this->cachedMode = $this->resolveMode();

        $handlers = $this->discoverWalHandlers();
        $partitionMap = $this->buildPartitionMap();
        $this->partitionMapRefreshedAt = time();

        $handlerCount = array_sum(array_map('count', $handlers));
        if (0 === $handlerCount) {
            $this->warn('No WAL handlers discovered. The command will poll but no events will be dispatched.');
        }

        /** 自动构建 add-tables 参数，仅输出被监听表的 WAL 变更 */
        if (!$this->option('no-add-tables')) {
            $this->autoAddTables = $this->buildAutoAddTables($handlers, $partitionMap);
        }

        $memoryLimit = max(1, intval($this->option('memory')));
        $maxErrors = max(0, intval($this->option('max-errors')));
        $partitionRefresh = max(0, intval($this->option('partition-refresh')));
        $slotLagWarning = max(0, intval($this->option('slot-lag-warning')));
        $slotLagCritical = max(0, intval($this->option('slot-lag-critical')));

        $this->info(sprintf(
            'WAL event dispatch started, slot: %s, mode: %s, queue: %s/%s, interval: %.2fs, batch: %d, memory: %dMB, max-errors: %s, tables: %d, handlers: %d, partitions: %d',
            $slot,
            $this->getMode(),
            $this->option('queue-connection'),
            $this->option('queue') ?: 'default',
            $interval,
            $batch,
            $memoryLimit,
            $maxErrors > 0 ? $maxErrors : 'unlimited',
            count($handlers),
            $handlerCount,
            count($partitionMap)
        ));
        foreach ($handlers as $table => $metas) {
            foreach ($metas as $meta) {
                $this->info(sprintf('  -> %s (table: %s, key: %s)', get_class($meta['handler']), $table, $meta['keyName']));
            }
        }
        if (null !== $this->autoAddTables) {
            $this->info(sprintf('Auto add-tables: %s', $this->autoAddTables));
        }

        while ($this->shouldRun) {
            $hasChanges = false;

            try {
                /** P0: WAL 堆积检测，在 poll 前检查 slot 健康状态 */
                if ($slotLagWarning > 0 || $slotLagCritical > 0) {
                    if ($this->checkSlotHealth($slot, $slotLagWarning, $slotLagCritical)) {
                        break;
                    }
                }

                $hasChanges = $this->pollChanges($slot, $handlers, $batch, $partitionMap);

                $this->errorStreak = 0;
                $this->flushTelescopeEntries();
            } catch (\Error $e) {
                /**
                 * P1: Error（TypeError/ParseError 等）表示代码逻辑错误，重试无意义。
                 * 直接报告并停止，避免浪费时间在无意义的指数退避上。
                 */
                $this->error(sprintf("Fatal error (not retryable): %s\n%s", $e->getMessage(), $e->getTraceAsString()));
                $this->getExceptionHandler()->report($e);
                break;
            } catch (Throwable $e) {
                $this->errorStreak++;

                $backoff = $this->getBackoffSeconds($this->errorStreak);
                $this->error(sprintf("Poll error (streak %d, backoff %ds): %s\n%s", $this->errorStreak, $backoff, $e->getMessage(), $e->getTraceAsString()));
                $this->getExceptionHandler()->report($e);
                $this->reconnectDatabase();

                /**
                 * WAL 段文件已被删除（PG 或 RDS WAL 保留策略回收），
                 * 复制槽的 restart_lsn 指向的 WAL 不存在，无法恢复。
                 * 删除旧槽，让 ensureSlotExists 从当前 LSN 重建。
                 * 会丢失旧槽 restart_lsn 到当前 LSN 之间的 WAL 事件。
                 */
                if ($this->isWalSegmentRemovedError($e)) {
                    $this->warn(sprintf(
                        'WAL segment removed for slot [%s], dropping and recreating from current LSN. '
                        .'WAL events between old restart_lsn and now are lost.',
                        $slot
                    ));

                    try {
                        $this->getConnection()->statement('SELECT pg_drop_replication_slot(?)', [$slot]);
                        $this->info(sprintf('Slot [%s] dropped due to WAL segment removal', $slot));
                    } catch (Throwable $de) {
                        $this->error(sprintf('Failed to drop slot [%s]: %s', $slot, $de->getMessage()));
                        /** drop 失败意味着旧槽仍在，ensureSlotExists 会静默跳过，slot 不会被修复 */
                        $this->error('Slot drop failed, skipping re-create to avoid masking the issue');

                        if ($maxErrors > 0 && $this->errorStreak >= $maxErrors) {
                            $this->error(sprintf('Consecutive errors reached limit (%d), stopping...', $maxErrors));
                            break;
                        }

                        usleep(intval($backoff * 1000000));
                        continue;
                    }
                }

                try {
                    $this->ensureSlotExists($slot);
                } catch (Throwable $re) {
                    $this->error(sprintf('Slot re-create failed: %s', $re->getMessage()));
                }

                if ($maxErrors > 0 && $this->errorStreak >= $maxErrors) {
                    $this->error(sprintf('Consecutive errors reached limit (%d), stopping...', $maxErrors));
                    break;
                }

                usleep(intval($backoff * 1000000));
                continue;
            }

            if ($this->isMemoryExceeded($memoryLimit)) {
                $this->info(sprintf(
                    'Memory limit of %dMB exceeded (current: %dMB), stopping...',
                    $memoryLimit,
                    intval(memory_get_usage(true) / 1024 / 1024)
                ));
                break;
            }

            /** 按用户配置的间隔刷新分区映射（默认 0 = 不自动刷新） */
            if ($partitionRefresh > 0 && time() - $this->partitionMapRefreshedAt >= $partitionRefresh) {
                $partitionMap = $this->buildPartitionMap();
                $this->partitionMapRefreshedAt = time();
                if (!$this->option('no-add-tables')) {
                    $oldAddTables = $this->autoAddTables;
                    $this->autoAddTables = $this->buildAutoAddTables($handlers, $partitionMap);
                    if ($this->autoAddTables !== $oldAddTables) {
                        $this->info(sprintf('Auto add-tables updated: %s', $this->autoAddTables ?: '(none)'));
                    }
                }
                $this->cachedWal2jsonParams = null;
            }

            /** P1: 清理 Laravel 容器作用域绑定，防止长驻进程内存泄漏 */
            $this->clearScopedInstances();

            if (!$hasChanges) {
                usleep(intval($interval * 1000000));
            }
        }

        $this->info('WAL event dispatch stopped');
    }

    /**
     * 扫描 Model 目录，收集实现 HasWalHandler 接口的 Model。
     *
     * 同一张表可以有多个 Handler（如 Question 和 OrgQuestion 共享 questions 表），
     * 每个 Handler 独立接收相同的 WAL 变更。
     *
     * 返回格式：[ 'table_name' => [ ['handler' => Model, 'keyName' => 'id'], ... ] ]
     *
     * @return array<string, array<int, array{handler: HasWalHandler, keyName: string}>>
     */
    protected function discoverWalHandlers(): array
    {
        $handlers = [];
        $paths = $this->getModelPaths();

        foreach ($paths as $dir => $namespace) {
            if (!is_dir($dir)) {
                $this->warn(sprintf('Model path does not exist: %s', $dir));
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ('php' !== $file->getExtension()) {
                    continue;
                }

                $pathname = str_replace('\\', '/', $file->getPathname());
                $baseDir = str_replace('\\', '/', $dir);
                $relativePath = substr($pathname, strlen($baseDir) + 1);
                $className = $namespace.str_replace('/', '\\', substr($relativePath, 0, -4));

                if (!class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                    if (!$reflection->isInstantiable()) {
                        continue;
                    }
                    if (!$reflection->implementsInterface(HasWalHandler::class)) {
                        continue;
                    }
                } catch (Throwable $e) {
                    continue;
                }

                /** @var HasWalHandler $instance */
                $instance = new $className();
                $keyName = method_exists($instance, 'getKeyName') ? $instance->getKeyName() : 'id';

                $handlers[$instance->getTable()][] = [
                    'handler' => $instance,
                    'keyName' => $keyName,
                ];
            }
        }

        return $handlers;
    }

    /**
     * 解析 --model-path 选项为 [绝对路径 => 命名空间] 映射。
     *
     * 格式：--model-path="app/Models:App\\Models"（冒号分隔路径和命名空间）
     * 省略命名空间时，自动从目录路径推导。
     *
     * @return array<string, string>
     */
    protected function getModelPaths(): array
    {
        $paths = $this->option('model-path');

        if (empty($paths)) {
            return [app_path('Models') => 'App\\Models\\'];
        }

        $result = [];
        foreach ($paths as $path) {
            if (false !== strpos($path, ':')) {
                list($dir, $ns) = explode(':', $path, 2);
            } else {
                $dir = $path;
                $ns = str_replace('/', '\\', ucfirst($dir));
            }

            $absDir = base_path($dir);
            $result[$absDir] = rtrim($ns, '\\').'\\';
        }

        return $result;
    }

    /**
     * 通过 pg_inherits 系统表构建 分区子表 → 父表 映射。
     *
     * PostgreSQL 分区表的 WAL 记录使用分区子表名（如 orders_p0），
     * 需要映射到父表名（orders）才能找到对应的 Handler。
     *
     * @return array<string, string> ['orders_p0' => 'orders', ...]
     */
    protected function buildPartitionMap(): array
    {
        try {
            /**
             * 使用递归 CTE 查询完整的继承链，支持多级分区（如先按 range 再按 list）。
             * 将所有叶子分区直接映射到顶层父表，跳过中间层。
             */
            $results = $this->getConnection()->select(
                "WITH RECURSIVE inheritance AS (
                    SELECT inhrelid, inhparent FROM pg_inherits
                    UNION ALL
                    SELECT i.inhrelid, ih.inhparent
                    FROM pg_inherits i
                    JOIN inheritance ih ON i.inhparent = ih.inhrelid
                )
                SELECT child_cls.relname AS child_table, parent_cls.relname AS parent_table
                FROM inheritance
                JOIN pg_class child_cls ON inheritance.inhrelid = child_cls.oid
                JOIN pg_class parent_cls ON inheritance.inhparent = parent_cls.oid
                WHERE child_cls.relkind = 'r'
                  AND NOT EXISTS (SELECT 1 FROM pg_inherits x WHERE x.inhparent = child_cls.oid)",
                [],
                false
            );

            $map = [];
            foreach ($results as $row) {
                $map[$row->child_table] = $row->parent_table;
            }

            return $map;
        } catch (Throwable $e) {
            $this->warn(sprintf('Failed to build partition map: %s', $e->getMessage()));

            return [];
        }
    }

    /**
     * 通过分区映射解析表名。非分区表原样返回。
     */
    protected function resolveTable(string $table, array $partitionMap): string
    {
        return $partitionMap[$table] ?? $table;
    }

    /**
     * 获取 WAL 消费模式（优先返回缓存值）。
     *
     * @return string auto|advance|peek
     */
    protected function getMode(): string
    {
        if (null !== $this->cachedMode) {
            return $this->cachedMode;
        }

        return $this->resolveMode();
    }

    /**
     * 解析并校验 --mode 选项。无效值输出警告并回退到 advance。
     *
     * @return string
     */
    protected function resolveMode(): string
    {
        $mode = $this->option('mode');
        $valid = [self::MODE_AUTO, self::MODE_ADVANCE, self::MODE_PEEK];

        if (in_array($mode, $valid, true)) {
            return $mode;
        }

        if (null !== $mode && '' !== $mode) {
            $this->warn(sprintf('Invalid mode "%s", falling back to "advance". Valid modes: %s', $mode, implode(', ', $valid)));
        }

        return self::MODE_ADVANCE;
    }

    /**
     * 单次轮询：读取 WAL → 解析变更 → 构建 WalChangeRecord → 分发事件 → 推进槽位。
     *
     * 流程：
     * 1. 通过 wal2json 流式读取 WAL 变更（统一使用 peek_changes）
     * 2. 解析 JSON，按表名分组构建 WalChangeRecord 数组
     * 3. 对每条记录 dispatch WalChangesDispatchJob，Job 内 dispatch 字符串事件
     * 4. auto/advance 模式下，推进槽位到最后处理的 LSN
     *
     * @param string $slot         复制槽名称
     * @param array  $handlers     Handler 映射 [table => [{handler, keyName}, ...]]
     * @param int    $batch        单次最大变更数
     * @param array  $partitionMap 分区表映射 [child => parent]
     *
     * @throws Throwable
     *
     * @return bool 是否有变更
     */
    protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
    {
        $connection = $this->getConnection();
        $mode = $this->getMode();

        /**
         * WAL 读取统一基于 peek_changes。
         *
         * get_changes 在大事务 + 游标分块 FETCH 场景下会出现游标提前失效，
         * 导致后续 FETCH 报 “cursor does not exist”。
         * 这里统一先 peek，再在成功处理后按模式决定是否 advance，
         * 保留 auto 模式“成功后消费”的外部语义。
         * 这样可以把“读取”和“确认消费”两个动作显式拆开，降低大事务流式读取时的游标风险。
         */
        $function = 'pg_logical_slot_peek_changes';

        /**
         * 防幻影滞后：peek 前记录当前 WAL LSN。
         *
         * 共享 PG 实例中，其他数据库的 WAL 会推进 pg_current_wal_lsn()，
         * 而本数据库的复制槽不动（无变更），导致监控指标显示持续增长的假性滞后。
         * peek 结果为空时，advance 到此 LSN——peek 已确认此 LSN 之前无遗漏数据。
         */
        $prePeekLsn = null;
        if (self::MODE_ADVANCE === $mode) {
            try {
                $row = $connection->selectOne('SELECT pg_current_wal_lsn() AS lsn', [], false);
                $prePeekLsn = null !== $row ? $row->lsn : null;
            } catch (Throwable $e) {
                /** best-effort */
            }
        }

        /**
         * 调用 wal2json 读取 WAL 变更。
         *
         * SQL: SELECT lsn, data FROM pg_logical_slot_peek_changes('slot', NULL, 1000, 'format-version', '2')
         * 第二个参数 NULL = 读到最新 LSN；第三个参数 = 最大变更数（注意：PG 不会拆分事务，
         * 单个大事务的变更数可能远超此值）；后续为 wal2json 插件参数（key, value 交替）。
         *
         * 通过 Connection::cursor() 逐行读取，避免单个大事务将全部结果加载到 PHP 内存。
         */
        $wal2json = $this->getCachedWal2jsonParams();
        $sql = sprintf('SELECT lsn, data FROM %s(?, NULL, ?%s)', $function, $wal2json[0]);
        $bindings = array_merge([$slot, $batch], $wal2json[1]);

        /**
         * 使用写连接流式读取 + 即时 dispatch。
         *
         * 默认优先走 Laravel Connection::cursor()，并显式指定 useReadPdo=false，
         * 保证整个命令都通过同一个 $connection 在主库上执行，不直接操作真实 PDO。
         *
         * 如果 Listener 有耗时操作（HTTP 调用、外部写入），建议使用
         * --queue-connection=redis 将 dispatch 推入队列，避免长事务阻塞 VACUUM。
         */
        $isV2 = '2' === $this->getEffectiveWal2jsonFormatVersion();

        $tableCounts = [];
        $dispatchCount = 0;
        $lastLsn = null;

        foreach ($connection->cursor($sql, $bindings, false, [\PDO::FETCH_OBJ]) as $row) {
            $lastLsn = $row->lsn;

            $payload = json_decode($row->data, true);
            if (!is_array($payload)) {
                if (JSON_ERROR_NONE !== json_last_error()) {
                    $this->warn(sprintf('json_decode failed (LSN %s): %s, data prefix: %s', $row->lsn, json_last_error_msg(), substr($row->data, 0, 200)));
                }
                continue;
            }

            $changes = $isV2 ? [$payload] : ($payload['change'] ?? []);
            foreach ($changes as $change) {
                $rawTable = $change['table'] ?? null;
                if (null === $rawTable) {
                    continue;
                }

                $resolvedTable = $this->resolveTable($rawTable, $partitionMap);
                if (!isset($handlers[$resolvedTable])) {
                    continue;
                }

                foreach ($handlers[$resolvedTable] as $meta) {
                    $record = WalChangeRecord::fromWal2json($change, $resolvedTable, $rawTable, $meta['keyName'], get_class($meta['handler']));
                    if (null === $record) {
                        continue;
                    }

                    $this->dispatchWalChange($record);

                    $table = $record->getTable();
                    $kind = $record->getKind();
                    $tableCounts[$table][$kind] = ($tableCounts[$table][$kind] ?? 0) + 1;
                    $dispatchCount++;
                }
            }
        }

        /** 无变更：advance 模式下推进到 prePeekLsn 消除幻影滞后 */
        if (null === $lastLsn) {
            if (null !== $prePeekLsn) {
                $this->advanceSlot($connection, $slot, $prePeekLsn);
            }

            return false;
        }

        if ($dispatchCount > 0) {
            $this->line(sprintf('[%s] %s', date('Y-m-d H:i:s'), $this->formatChangeSummary($tableCounts)));
        }

        /** auto/advance 模式：事件分发成功后，手动推进槽位到最后处理的 LSN */
        if (in_array($mode, [self::MODE_AUTO, self::MODE_ADVANCE], true)) {
            $this->advanceSlot($connection, $slot, $lastLsn);
        }

        return $dispatchCount > 0;
    }

    /**
     * 收集所有自动生成的 wal2json 参数。
     *
     * @return array<string, string> [key => value]
     */
    protected function collectAutoWal2jsonParams()
    {
        $userKeys = $this->getUserWal2jsonParamKeys();
        $params = [];

        /** format-version（用户通过 --wal2json-params 手动指定时跳过，避免重复） */
        if (!in_array('format-version', $userKeys, true)) {
            $formatVersion = $this->option('format-version');
            if (!empty($formatVersion)) {
                $params['format-version'] = $formatVersion;
            }
        }

        /** add-tables（用户通过 --wal2json-params 手动指定时跳过） */
        if (!in_array('add-tables', $userKeys, true) && null !== $this->autoAddTables) {
            $params['add-tables'] = $this->autoAddTables;
        }

        /** filter-tables 黑名单（用户通过 --wal2json-params 手动指定时跳过） */
        if (!in_array('filter-tables', $userKeys, true)) {
            $filterTables = $this->option('filter-tables');
            if (!empty($filterTables)) {
                $params['filter-tables'] = implode(',', array_map([$this, 'qualifyTableName'], $filterTables));
            }
        }

        return $params;
    }

    /**
     * 获取缓存的 wal2json 参数，缓存未命中时构建并缓存。
     *
     * @return array{string, array} [placeholders, bindings]
     */
    protected function getCachedWal2jsonParams()
    {
        if (null !== $this->cachedWal2jsonParams) {
            return $this->cachedWal2jsonParams;
        }

        return $this->cachedWal2jsonParams = $this->buildWal2jsonParams();
    }

    /**
     * 一次性构建 wal2json 参数的 SQL 占位符和绑定值。
     *
     * 合并用户参数和自动参数，避免 collectAutoWal2jsonParams 被分别调用两次
     * 导致占位符数量与绑定值不匹配的理论风险。
     *
     * @return array{string, array} [placeholders, bindings]
     */
    protected function buildWal2jsonParams()
    {
        $bindings = [];

        $params = $this->option('wal2json-params');
        if (!empty($params)) {
            foreach ($params as $param) {
                list($key, $value) = self::parseWal2jsonParam($param);
                $bindings[] = $key;
                $bindings[] = $value;
            }
        }

        foreach ($this->collectAutoWal2jsonParams() as $key => $value) {
            $bindings[] = $key;
            $bindings[] = $value;
        }

        $count = intval(count($bindings) / 2);
        $placeholders = $count > 0 ? str_repeat(', ?, ?', $count) : '';

        return [$placeholders, $bindings];
    }

    /**
     * 返回最终生效的 wal2json format-version。
     *
     * 解析逻辑必须与实际传给 wal2json 的参数保持一致，不能只看命令选项；
     * 否则用户通过 --wal2json-params 覆盖 format-version 时，会出现
     * “插件按 v1 输出、PHP 按 v2 解析”的分裂。
     */
    protected function getEffectiveWal2jsonFormatVersion(): string
    {
        list(, $bindings) = $this->getCachedWal2jsonParams();

        for ($i = 0, $count = count($bindings); $i < $count; $i += 2) {
            if ('format-version' === $bindings[$i]) {
                return (string) ($bindings[$i + 1] ?? '');
            }
        }

        return '1';
    }

    /**
     * 提取用户通过 --wal2json-params 手动指定的参数 key 列表。
     *
     * 用于 collectAutoWal2jsonParams 检测冲突，避免自动参数覆盖用户的手动设置。
     *
     * @return string[]
     */
    protected function getUserWal2jsonParamKeys()
    {
        $keys = [];
        foreach ($this->option('wal2json-params') ?: [] as $param) {
            $keys[] = self::parseWal2jsonParam($param)[0];
        }

        return $keys;
    }

    /**
     * 解析单个 wal2json 参数字符串为 [key, value] 对。
     *
     * @param string $param 如 "filter-columns=content,body" 或 "include-lsn"
     *
     * @return array{string, string} [key, value]
     */
    protected static function parseWal2jsonParam($param)
    {
        $parts = explode('=', $param, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * 根据被监听的表名和分区映射自动构建 add-tables 参数。
     *
     * wal2json 的 add-tables 参数为白名单，只输出指定表的变更。
     * WAL 中分区表使用子表名（如 user_syllabuses_p056），不是父表名，
     * 因此需要把被监听的父表展开为所有分区子表名。
     *
     * @param array $handlers     Handler 映射 [table => [{handler, keyName}, ...]]
     * @param array $partitionMap 分区表映射 [child => parent]
     *
     * @return string|null add-tables 值，无被监听表时返回 null
     */
    protected function buildAutoAddTables(array $handlers, array $partitionMap = [])
    {
        $handlerTables = array_keys($handlers);

        if (empty($handlerTables)) {
            return null;
        }

        /**
         * 构建 parent → [child1, child2, ...] 反向映射。
         * 非分区表没有子表，直接使用父表名。
         */
        $parentToChildren = [];
        foreach ($partitionMap as $child => $parent) {
            $parentToChildren[$parent][] = $child;
        }

        $allTables = [];
        foreach ($handlerTables as $table) {
            if (isset($parentToChildren[$table])) {
                /** 分区表：展开为所有子表名 */
                foreach ($parentToChildren[$table] as $child) {
                    $allTables[] = $child;
                }
            } else {
                /** 非分区表：直接使用 */
                $allTables[] = $table;
            }
        }

        return implode(',', array_map([$this, 'qualifyTableName'], $allTables));
    }

    /**
     * 推进复制槽到指定 LSN，失败时仅记录警告不中断流程。
     *
     * @param Connection $connection
     * @param string     $slot
     * @param string     $lsn
     */
    protected function advanceSlot($connection, $slot, $lsn): void
    {
        try {
            $connection->statement('SELECT pg_replication_slot_advance(?, ?)', [$slot, $lsn]);
        } catch (Throwable $e) {
            $this->warn(sprintf('Slot advance warning (LSN %s): %s', $lsn, $e->getMessage()));
        }
    }

    /**
     * 分发单条 WAL 变更记录。
     *
     * 提取为独立方法便于子类覆盖（如测试中替换为计数器跳过真实 Job dispatch）。
     *
     * @param WalChangeRecord $record
     */
    protected function dispatchWalChange(WalChangeRecord $record): void
    {
        $job = new WalChangesDispatchJob($record);
        $job->onConnection($this->option('queue-connection'));
        $job->onQueue($this->option('queue'));

        app('Illuminate\Contracts\Bus\Dispatcher')->dispatch($job);
    }

    /**
     * 将 [table => [kind => count]] 计数器格式化为可读摘要。
     *
     * @param array<string, array<string, int>> $tableCounts
     *
     * @return string 如 "users: 3 inserts, 2 updates; orders: 1 delete"
     */
    protected function formatChangeSummary(array $tableCounts): string
    {
        $tableParts = [];
        foreach ($tableCounts as $table => $kinds) {
            $kindParts = [];
            foreach ($kinds as $kind => $count) {
                $kindParts[] = sprintf('%d %s%s', $count, $kind, $count > 1 ? 's' : '');
            }
            $tableParts[] = sprintf('%s: %s', $table, implode(', ', $kindParts));
        }

        return implode('; ', $tableParts);
    }

    /**
     * 生成复制槽名称。
     *
     * 指定 --slot 时直接使用，否则自动生成：{app_name}_{app_env}_wal_event，
     * 非字母数字字符替换为下划线。
     */
    protected function getSlotName(): string
    {
        $slot = $this->option('slot');
        if (!empty($slot)) {
            return $slot;
        }

        $appName = config('app.name') ?: 'app';
        $appEnv = config('app.env') ?: 'production';

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName.'_'.$appEnv));
        $slug = preg_replace('/_+/', '_', trim($slug, '_'));

        return $slug.'_wal_event';
    }

    /**
     * 获取数据库连接，强制所有查询走写连接（主库）。
     *
     * WAL 命令的所有操作（游标、WAL 函数调用、slot 管理）都必须在主库上执行。
     * useWriteConnectionWhenReading() 是 Laravel 原生 API，
     * 让 getReadPdo() 返回写连接 PDO，所有 select() 也走主库。
     *
     * @return Connection
     */
    protected function getConnection()
    {
        $connection = app('db')->connection($this->option('connection') ?: null);
        $connection->useWriteConnectionWhenReading();

        return $connection;
    }

    /**
     * 注册 SIGTERM/SIGINT 信号处理器，实现优雅关闭。
     */
    protected function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function () {
            $this->shouldRun = false;
        });
        pcntl_signal(SIGINT, function () {
            $this->shouldRun = false;
        });
    }

    /**
     * @param int $memoryLimit MB
     */
    protected function isMemoryExceeded($memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * 为表名补全 schema 前缀。
     *
     * wal2json 的 add-tables/filter-tables 参数要求 schema.table 格式，
     * 未指定 schema 的表名默认加 public 前缀。
     *
     * @param string $table
     *
     * @return string
     */
    protected function qualifyTableName($table)
    {
        return false === strpos($table, '.') ? 'public.'.$table : $table;
    }

    /**
     * 根据连续错误次数计算指数退避秒数。
     *
     * 1s → 2s → 4s → 8s → 16s → 32s → 60s（上限）
     *
     * @param int $errorStreak
     *
     * @return int
     */
    protected function getBackoffSeconds($errorStreak)
    {
        return intval(min(60, pow(2, max(0, $errorStreak - 1))));
    }

    /**
     * 长驻进程手动刷入 Telescope 条目。
     *
     * Telescope 仅在 $app->terminating() 时刷入，长驻进程永远不会触发该事件。
     * 每次轮询成功后显式调用 store()，确保 Telescope 数据不会积压在内存中。
     * Telescope 可用性在首次检查后缓存，避免每次轮询都走 class_exists。
     */
    protected function flushTelescopeEntries(): void
    {
        $telescopeClass = $this->getTelescopeClass();
        if (null === $telescopeClass) {
            return;
        }

        if (!call_user_func([$telescopeClass, 'isRecording'])) {
            return;
        }

        try {
            call_user_func([$telescopeClass, 'store'], app('Laravel\Telescope\Contracts\EntriesRepository'));
        } catch (Throwable $e) {
            /** best-effort */
        }
    }

    /**
     * 返回 Telescope 类名；未安装 Telescope 时返回 null。
     *
     * @return string|null
     */
    protected function getTelescopeClass()
    {
        if (null === $this->telescopeAvailable) {
            $this->telescopeAvailable = class_exists('Laravel\Telescope\Telescope');
        }

        if (!$this->telescopeAvailable) {
            return null;
        }

        return 'Laravel\Telescope\Telescope';
    }

    /**
     * 重连数据库。轮询异常时调用，尝试恢复连接。
     */
    protected function reconnectDatabase(): void
    {
        try {
            $this->getConnection()->reconnect();
            $this->info('Database reconnected');
        } catch (Throwable $e) {
            $this->error(sprintf('Database reconnect failed: %s', $e->getMessage()));
        }
    }

    /**
     * 检查复制槽健康状态，检测 WAL 堆积和 slot 失效。
     *
     * @param string $slot       复制槽名称
     * @param int    $warningMB  警告阈值（MB），0 表示不检查
     * @param int    $criticalMB 严重阈值（MB），0 表示不检查，超过时返回 true
     *
     * @return bool true 表示应停止进程
     */
    protected function checkSlotHealth($slot, $warningMB = 0, $criticalMB = 0)
    {
        try {
            $row = $this->getConnection()->selectOne(
                'SELECT pg_wal_lsn_diff(pg_current_wal_lsn(), confirmed_flush_lsn) AS lag_bytes
                 FROM pg_replication_slots
                 WHERE slot_name = ?',
                [$slot],
                false
            );

            if (null === $row || null === $row->lag_bytes) {
                return false;
            }

            $lagMB = intval($row->lag_bytes / 1024 / 1024);

            if ($criticalMB > 0 && $lagMB >= $criticalMB) {
                $this->error(sprintf(
                    'Slot [%s] WAL lag %dMB exceeds critical threshold %dMB, stopping to prevent disk exhaustion',
                    $slot,
                    $lagMB,
                    $criticalMB
                ));

                return true;
            }

            if ($warningMB > 0 && $lagMB >= $warningMB) {
                $this->warn(sprintf(
                    'Slot [%s] WAL lag %dMB exceeds warning threshold %dMB',
                    $slot,
                    $lagMB,
                    $warningMB
                ));
            }
        } catch (Throwable $e) {
            /** best-effort：检查失败不影响主流程 */
        }

        return false;
    }

    /**
     * 清理 Laravel 容器作用域绑定，防止长驻进程内存泄漏。
     */
    protected function clearScopedInstances(): void
    {
        if (method_exists($this->getLaravel(), 'forgetScopedInstances')) {
            $this->getLaravel()->forgetScopedInstances();
        }
    }

    /**
     * 检测是否为 WAL 段文件已被删除的错误。
     *
     * PostgreSQL 错误码 58P01 (Undefined File) + 消息包含 "has already been removed"。
     * 阿里云 RDS 的 WAL 保留策略可能在复制槽消费前删除 WAL 段文件，
     * 导致 peek_changes/get_changes 无法读取，此时需要重建复制槽。
     */
    protected function isWalSegmentRemovedError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return false !== stripos($message, 'has already been removed')
            || (false !== stripos($message, '58P01') && false !== stripos($message, 'WAL segment'));
    }

    /**
     * 确保复制槽存在，不存在则创建（使用 wal2json 插件）。
     */
    protected function ensureSlotExists(string $slot): void
    {
        $connection = $this->getConnection();

        $exists = $connection->selectOne(
            'SELECT plugin, active, active_pid FROM pg_replication_slots WHERE slot_name = ? AND slot_type = ?',
            [$slot, 'logical'],
            false
        );

        if (null !== $exists) {
            if ('wal2json' !== $exists->plugin) {
                throw new \RuntimeException(sprintf(
                    'Slot [%s] exists but uses plugin [%s] instead of wal2json',
                    $slot,
                    $exists->plugin
                ));
            }
            if ($exists->active) {
                throw new \RuntimeException(sprintf(
                    'Slot [%s] is currently active (PID %s), another consumer is running. '
                    .'Only one consumer can use a replication slot at a time.',
                    $slot,
                    $exists->active_pid
                ));
            }

            return;
        }

        $this->info(sprintf('Slot [%s] does not exist, creating...', $slot));

        try {
            $connection->statement(
                "SELECT pg_create_logical_replication_slot(?, 'wal2json')",
                [$slot]
            );
            $this->info(sprintf('Slot [%s] created successfully', $slot));
        } catch (Throwable $e) {
            /** 并发竞态：另一个进程可能在检查与创建之间已创建了同名 slot */
            $exists = $connection->selectOne(
                'SELECT 1 FROM pg_replication_slots WHERE slot_name = ?',
                [$slot],
                false
            );
            if (null !== $exists) {
                $this->info(sprintf('Slot [%s] already exists (created by another process)', $slot));

                return;
            }

            throw $e;
        }
    }
}
