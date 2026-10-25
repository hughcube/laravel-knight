# WAL Change Record 设计方案

## 概述

增强 `WalEventDispatchCommand` 的 WAL 事件分发机制，从只传递主键 ID 改为传递完整的变更记录（`WalChangeRecord`），解决三个核心问题：

1. **分区表查询效率** — 不再需要回查数据库，彻底消除分区表全分区扫描
2. **变更详情透传** — 业务层能获知操作类型（INSERT/UPDATE/DELETE）、变更列和旧值
3. **事件驱动解耦** — 移除 `onKnightWalChanged()` 钩子，改由应用层通过字符串事件（`wal:{table}:{kind}`）自由订阅

## 设计决策记录

| 决策项 | 结论 | 理由 |
|--------|------|------|
| 向后兼容 | 破坏性变更 | 接口简洁优先，用户确认可接受 |
| 同 ID 多次变更 | 保留所有记录 | 通用扩展包不应替用户丢弃信息 |
| 列数据携带策略 | 默认携带完整数据，通过 wal2json 参数在源头过滤 | 代码层不做二次过滤，优化交给 PG 配置 |
| Listener 回查 DB | 不再回查，用 WAL 数据构建 model | 彻底消除分区扫描，减少 DB 负载 |
| onKnightWalChanged | 从接口和 trait 中移除 | 业务副作用不属于 Model 职责，应由应用层事件 Listener 处理 |
| modelClass 放入 WalChangeRecord | 是 | 下游不需要额外传参，`makeModel()` 一步到位 |
| 事件分发模式 | 命令参数 `--dispatch=sync\|queue` 控制 | 同步适合调试/轻量场景，队列适合生产环境 |

## 重要说明：REPLICA IDENTITY 与 DELETE 事件

默认 `REPLICA IDENTITY DEFAULT` 下，PostgreSQL WAL 对 DELETE 操作的 `oldkeys` **只包含主键列**。这意味着：

- **DELETE 事件的 `oldColumns` 通常只有主键**，`makeModel()` 构建的 model 只会被填充主键值
- **INSERT/UPDATE 不受影响**，wal2json 始终返回完整新行数据
- 如需 DELETE 时获取完整旧行数据，须在 PG 端配置：
  ```sql
  -- 完整旧行（WAL 体积增大）
  ALTER TABLE your_table REPLICA IDENTITY FULL;
  -- 或只记录特定唯一索引列（折中方案）
  ALTER TABLE your_table REPLICA IDENTITY USING INDEX your_unique_index;
  ```

## 改动清单

### 1. 新增：WalChangeRecord 值对象

**文件：** `src/Database/Wal/WalChangeRecord.php`

```php
namespace HughCube\Laravel\Knight\Database\Wal;

use Illuminate\Database\Eloquent\Model;

class WalChangeRecord
{
    const KIND_INSERT = 'insert';
    const KIND_UPDATE = 'update';
    const KIND_DELETE = 'delete';

    /** @var string insert|update|delete */
    private $kind;

    /** @var string 解析后的父表名 */
    private $table;

    /** @var string|null 原始分区子表名（非分区时为 null） */
    private $partitionTable;

    /** @var int|string 主键值 */
    private $id;

    /** @var string 主键列名 */
    private $keyName;

    /** @var array<string, mixed> 新值列映射（INSERT/UPDATE 有值，DELETE 为空数组） */
    private $columns;

    /**
     * 旧值列映射。
     * - DELETE: 来自 oldkeys，默认 REPLICA IDENTITY 下只包含主键列
     * - UPDATE: 需 REPLICA IDENTITY FULL 才有旧值
     * - INSERT: 始终为空数组
     *
     * @var array<string, mixed>
     */
    private $oldColumns;

    /** @var string Model 类名（如 App\Models\Question） */
    private $modelClass;

    public function __construct($kind, $table, $partitionTable, $id, $keyName, array $columns, array $oldColumns, $modelClass)
    {
        if (!in_array($kind, [self::KIND_INSERT, self::KIND_UPDATE, self::KIND_DELETE], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid WAL change kind: %s', $kind));
        }

        $this->kind = $kind;
        $this->table = $table;
        $this->partitionTable = $partitionTable;
        $this->id = $id;
        $this->keyName = $keyName;
        $this->columns = $columns;
        $this->oldColumns = $oldColumns;
        $this->modelClass = $modelClass;
    }

    /** @return string */
    public function getKind() { return $this->kind; }

    /** @return string */
    public function getTable() { return $this->table; }

    /** @return string|null */
    public function getPartitionTable() { return $this->partitionTable; }

    /** @return int|string */
    public function getId() { return $this->id; }

    /** @return string */
    public function getKeyName() { return $this->keyName; }

    /** @return array<string, mixed> */
    public function getColumns() { return $this->columns; }

    /** @return array<string, mixed> */
    public function getOldColumns() { return $this->oldColumns; }

    /** @return string */
    public function getModelClass() { return $this->modelClass; }

    /** @return bool */
    public function isInsert() { return self::KIND_INSERT === $this->kind; }

    /** @return bool */
    public function isUpdate() { return self::KIND_UPDATE === $this->kind; }

    /** @return bool */
    public function isDelete() { return self::KIND_DELETE === $this->kind; }

    /** @return bool */
    public function isFromPartition() { return null !== $this->partitionTable; }

    /**
     * 用 WAL 数据构建 Model 实例，不回查 DB。
     *
     * - INSERT/UPDATE: forceFill(columns)，包含完整新行数据
     * - DELETE: forceFill(oldColumns)，默认 REPLICA IDENTITY 下只有主键
     * - exists 标记：UPDATE=true，INSERT/DELETE=false
     *
     * @return Model
     */
    public function makeModel()
    {
        $class = $this->modelClass;
        $model = new $class();

        if ($this->isDelete()) {
            $model->forceFill($this->oldColumns);
        } else {
            $model->forceFill($this->columns);
        }

        $model->{$this->keyName} = $this->id;
        $model->syncOriginal();
        $model->exists = $this->isUpdate();

        return $model;
    }
}
```

### 2. 修改：WalChangesDetected 事件

**文件：** `src/Events/WalChangesDetected.php`

- 移除 `$ids` 属性
- 新增 `$changes` 属性（`WalChangeRecord[]`）
- 属性改为 private + getter

```php
namespace HughCube\Laravel\Knight\Events;

use HughCube\Laravel\Knight\Contracts\Database\HasWalHandler;
use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;

class WalChangesDetected
{
    /** @var HasWalHandler */
    private $handler;

    /** @var WalChangeRecord[] */
    private $changes;

    public function __construct(HasWalHandler $handler, array $changes)
    {
        $this->handler = $handler;
        $this->changes = $changes;
    }

    /** @return HasWalHandler */
    public function getHandler() { return $this->handler; }

    /** @return WalChangeRecord[] */
    public function getChanges() { return $this->changes; }
}
```

### 3. 修改：HasWalHandler 接口

**文件：** `src/Contracts/Database/HasWalHandler.php`

- **移除** `onKnightWalChanged()` 方法声明（如之前存在）
- 接口只保留两个方法

```php
namespace HughCube\Laravel\Knight\Contracts\Database;

interface HasWalHandler
{
    /** @return string */
    public function getTable();

    public function onKnightModelChanged(): void;
}
```

### 4. 修改：Model trait

**文件：** `src/Database/Eloquent/Traits/Model.php`

- 移除 `onKnightWalChanged()` 方法（含空实现）

### 5. 修改：HasWalHandlerTrait

**文件：** `src/Database/Eloquent/Traits/HasWalHandlerTrait.php`

当前为空标记 trait，无需改动。

### 6. 修改：WalChangesListener

**文件：** `src/Listeners/WalChangesListener.php`

- 不再回查数据库
- 用 `WalChangeRecord::makeModel()` 构建 model
- 只调用 `onKnightModelChanged()`（不再调用 `onKnightWalChanged`）

```php
namespace HughCube\Laravel\Knight\Listeners;

use HughCube\Laravel\Knight\Events\WalChangesDetected;

class WalChangesListener
{
    public function handle(WalChangesDetected $event): void
    {
        foreach ($event->getChanges() as $record) {
            $model = $record->makeModel();
            $model->onKnightModelChanged();
        }
    }
}
```

### 7. 修改：WalEventDispatchCommand

**文件：** `src/Console/Commands/WalEventDispatchCommand.php`

#### 7.1 新增命令选项

```
{--dispatch=sync : Event dispatch mode: sync (in WAL process) or queue (via job)}
{--queue= : Queue name (only effective with --dispatch=queue)}
{--queue-connection= : Queue connection (only effective with --dispatch=queue)}
{--wal2json-params=* : wal2json plugin parameters, e.g. --wal2json-params="filter-columns=content,body"}
```

#### 7.2 pollChanges() 重构

完整的变更记录构建逻辑：

```php
/** @var array<string, WalChangeRecord[]> $tableChanges */
$tableChanges = [];
$lastLsn = null;

foreach ($results as $row) {
    $lastLsn = $row->lsn;
    $payload = json_decode($row->data, true);

    if (!is_array($payload) || empty($payload['change'])) {
        continue;
    }

    foreach ($payload['change'] as $change) {
        $rawTable = isset($change['table']) ? $change['table'] : null;
        if (null === $rawTable) {
            continue;
        }

        $resolvedTable = $this->resolveTable($rawTable, $partitionMap);
        if (!isset($handlers[$resolvedTable])) {
            continue;
        }

        $keyName = $handlers[$resolvedTable][0]['keyName'];
        $kind = isset($change['kind']) ? $change['kind'] : null;

        // 构建列映射
        $columns = $this->buildColumnMap($change);
        $oldColumns = $this->buildOldColumnMap($change);

        // 提取主键：DELETE 从 oldColumns，INSERT/UPDATE 从 columns
        if ('delete' === $kind) {
            $id = isset($oldColumns[$keyName]) ? $oldColumns[$keyName] : null;
        } else {
            $id = isset($columns[$keyName]) ? $columns[$keyName] : null;
        }

        if (null === $id) {
            continue;
        }

        // 分区表：rawTable 与 resolvedTable 不同时记录原始子表名
        $partitionTable = ($rawTable !== $resolvedTable) ? $rawTable : null;

        // modelClass 从 handler 获取
        $modelClass = get_class($handlers[$resolvedTable][0]['handler']);

        $tableChanges[$resolvedTable][] = new WalChangeRecord(
            $kind, $resolvedTable, $partitionTable, $id, $keyName, $columns, $oldColumns, $modelClass
        );
    }
}

// dispatch
foreach ($tableChanges as $table => $changes) {
    foreach ($handlers[$table] as $meta) {
        $event = new WalChangesDetected($meta['handler'], $changes);

        if ('queue' === $this->option('dispatch')) {
            WalChangesDispatchJob::dispatch($event)
                ->onQueue($this->option('queue'))
                ->onConnection($this->option('queue-connection'));
        } else {
            $this->getEventsDispatcher()->dispatch($event);
        }
    }
}
```

- 移除 `extractPrimaryKey()` 方法
- 新增辅助方法：

```php
/** @return array<string, mixed> */
protected function buildColumnMap(array $change): array
{
    $names = isset($change['columnnames']) ? $change['columnnames'] : [];
    $values = isset($change['columnvalues']) ? $change['columnvalues'] : [];

    if (empty($names) || count($names) !== count($values)) {
        return [];
    }

    return array_combine($names, $values);
}

/** @return array<string, mixed> */
protected function buildOldColumnMap(array $change): array
{
    $names = isset($change['oldkeys']['keynames']) ? $change['oldkeys']['keynames'] : [];
    $values = isset($change['oldkeys']['keyvalues']) ? $change['oldkeys']['keyvalues'] : [];

    if (empty($names) || count($names) !== count($values)) {
        return [];
    }

    return array_combine($names, $values);
}
```

#### 7.3 新增：WalChangesDispatchJob

**文件：** `src/Jobs/WalChangesDispatchJob.php`

用于 `--dispatch=queue` 模式，将 `WalChangesDetected` 事件序列化后在队列 Worker 中触发。

```php
namespace HughCube\Laravel\Knight\Jobs;

use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;
use HughCube\Laravel\Knight\Events\WalChangesDetected;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class WalChangesDispatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var string handler 的 Model 类名 */
    private $handlerClass;

    /** @var WalChangeRecord[] */
    private $changes;

    public function __construct(WalChangesDetected $event)
    {
        $this->handlerClass = get_class($event->getHandler());
        $this->changes = $event->getChanges();
    }

    public function handle(EventsDispatcher $dispatcher): void
    {
        $class = $this->handlerClass;
        /** @var \HughCube\Laravel\Knight\Contracts\Database\HasWalHandler $handler */
        $handler = new $class();
        $dispatcher->dispatch(new WalChangesDetected($handler, $this->changes));
    }
}
```

**序列化安全**：`handlerClass` 是 string，`WalChangeRecord[]` 内部全是标量和数组（不含 Model 实例），原生 PHP 序列化即可，无需 `SerializesModels`。

#### 7.4 wal2json 参数透传

通过 `--wal2json-params` 以 `key=value` 形式传入，转换为 PG 函数的额外绑定参数。

> **注意：** `filter-columns` 是**排除列表**（指定要从输出中移除的列），`add-columns` 是**包含列表**。详见 [wal2json 文档](https://github.com/eulerto/wal2json)。

```php
protected function buildWal2jsonParamPlaceholders(): string
{
    $params = $this->option('wal2json-params');
    if (empty($params)) {
        return '';
    }
    $placeholders = '';
    foreach ($params as $param) {
        $placeholders .= ', ?, ?';
    }
    return $placeholders;
}

protected function buildWal2jsonParamBindings(): array
{
    $params = $this->option('wal2json-params');
    if (empty($params)) {
        return [];
    }
    $bindings = [];
    foreach ($params as $param) {
        $parts = explode('=', $param, 2);
        $bindings[] = $parts[0];
        $bindings[] = isset($parts[1]) ? $parts[1] : '';
    }
    return $bindings;
}
```

SQL 调用变为：

```php
$results = $connection->select(
    sprintf('SELECT lsn, data FROM %s(?, NULL, ?%s)', $function, $this->buildWal2jsonParamPlaceholders()),
    array_merge([$slot, $batch], $this->buildWal2jsonParamBindings())
);
```

#### 7.5 日志输出调整

由于不再 dedup，逐条记录 ID 可能冗长。改为按操作类型汇总：

```php
// 示例输出: [2026-03-25 10:00:00] App\Models\Order (key: id) 3 inserts, 2 updates, 1 delete
```

## `--dispatch` 模式行为对比

| | `--dispatch=sync` | `--dispatch=queue`（默认） |
|---|---|---|
| WalChangesDetected 触发位置 | WAL 命令进程 | 队列 Worker（通过 WalChangesDispatchJob） |
| 应用层 Listener 执行位置 | WAL 命令进程内同步 | Worker 内同步 |
| WAL 消费延迟 | 受 Listener 执行时间影响 | 几乎无阻塞 |
| 适用场景 | 调试、Listener 极轻量 | 生产环境 |

`--queue` 和 `--queue-connection` 仅在 `--dispatch=queue` 时生效，`sync` 模式下忽略。

## 内存控制

大字段表的内存问题通过 wal2json 在 PG 源头过滤解决：

```bash
# filter-columns: 排除指定列（不出现在输出中）
php artisan wal:event-dispatch \
    --wal2json-params="filter-columns=content,raw_body,large_json"

# add-columns: 只包含指定列（仅这些列出现在输出中）
php artisan wal:event-dispatch \
    --wal2json-params="add-columns=id,status,updated_at"
```

现有的 `--batch` 和 `--memory` 选项提供额外保护。

## 应用层推荐用法：字符串事件驱动

Knight 提供 `WalChangesDetected` 事件 + `WalChangeRecord`（含 `makeModel()` 和 `getModelClass()`）。应用层推荐将其拆分为细粒度的字符串事件：

### 事件名格式

```
wal:{table}:{kind}
```

示例：`wal:questions:insert`、`wal:tags:update`、`wal:org_users:delete`

### 分发 Listener

应用层注册一个 Listener 监听 `WalChangesDetected`，将批量事件拆分为逐条字符串事件：

```php
// App\Listeners\DispatchWalEventsListener
class DispatchWalEventsListener
{
    public function handle(WalChangesDetected $event): void
    {
        foreach ($event->getChanges() as $record) {
            event(sprintf('wal:%s:%s', $record->getTable(), $record->getKind()), [$record]);
        }
    }
}
```

### 业务 Listener 订阅

```php
// EventServiceProvider::boot()
Event::listen('wal:*', ClearModelCacheListener::class);             // 通配：所有表清缓存
Event::listen('wal:questions:insert', SyncQuestionListener::class);  // 精确匹配
Event::listen('wal:questions:update', SyncQuestionListener::class);
Event::listen('wal:tags:*', RefreshEntityPermissionsListener::class); // 表级通配
```

### 业务 Listener 实现

```php
// 精确匹配 listener — 直接收 WalChangeRecord
class SyncQuestionListener
{
    public function handle(WalChangeRecord $record): void
    {
        SyncQuestionToOtsJob::dispatch(['ids' => [$record->getId()]]);
    }
}

// 通配符 listener — 收 (string $eventName, array $payload)
class ClearModelCacheListener
{
    public function handle(string $eventName, array $payload): void
    {
        [$record] = $payload;
        $record->makeModel()->onKnightModelChanged();
    }
}

// 需要 Model 属性的 listener — 用 makeModel() 一步到位
class RefreshEntityPermissionsListener
{
    public function handle(string $eventName, array $payload): void
    {
        [$record] = $payload;
        $model = $record->makeModel();

        RefreshEntityRelatedPermissionsJob::dispatch([
            'org_id' => $model->org_id,
            'target_type' => $model->getAssignmentTargetType(),
            'entity_id' => $model->id,
            'parent_ids' => $model->getParents()->filter()->unique()->values()->all(),
        ]);
    }
}
```

关键优势：
- **Model 不承担业务副作用** — 没有 `onKnightWalChanged()`，Model 只负责 `onKnightModelChanged()`（清缓存）
- **零映射表** — 事件名路由已过滤表名，`makeModel()` 直接给完整 Model 实例，`getModelClass()` 给类名
- **加副作用只改 EventServiceProvider** — 新业务挂个 Listener，不动任何现有代码
- **DELETE 天然分离** — 不想处理 delete 就不订阅 `wal:xxx:delete`

## 测试策略

### 需要更新的测试文件

| 文件 | 改动 |
|------|------|
| `tests/Console/Commands/WalEventDispatchCommandTest.php` | `extractPrimaryKey` 测试替换为 `buildColumnMap`/`buildOldColumnMap` 测试 |
| `tests/Events/WalChangesDetectedTest.php` | `$ids` 改为 `$changes`（`WalChangeRecord[]`） |
| `tests/Listeners/WalChangesListenerTest.php` | 重写：不再依赖 DB 查询，改为验证 `makeModel()` + `onKnightModelChanged()` |
| `tests/Database/Eloquent/Traits/ModelWalHooksTest.php` | 移除 `onKnightWalChanged` 相关测试 |
| `tests/Console/Commands/WalEventDispatchCommandBehaviorTest.php` | 适配新的事件结构 |
| `tests/Console/Commands/WalEventDispatchIntegrationTest.php` | 适配新的事件结构 |

### 新增测试覆盖

- **WalChangeRecord 单元测试**：构造、getter、`isInsert()`/`isUpdate()`/`isDelete()`/`isFromPartition()`、`getModelClass()`
- **WalChangeRecord::makeModel() 测试**：INSERT/UPDATE/DELETE 三种场景的 model 填充、`exists` 标记、主键赋值
- **WalChangesDispatchJob 测试**：序列化/反序列化、事件触发、队列参数透传
- **wal2json 参数构建**：`buildWal2jsonParamPlaceholders()` / `buildWal2jsonParamBindings()` 的参数解析
- **分区表 partitionTable 传递**：验证 resolvedTable 与 rawTable 不同时 partitionTable 正确设置
- **--dispatch 模式测试**：sync 直接触发、queue 走 WalChangesDispatchJob

## PHP 兼容性

所有代码兼容 PHP 7.4+：
- 不使用命名参数、match 表达式、联合类型声明、nullsafe 操作符
- 属性不使用类型声明（通过 PHPDoc 注解提供类型信息）
- 类常量代替枚举（`KIND_INSERT`/`KIND_UPDATE`/`KIND_DELETE`）
- `makeModel()` 中使用 `$class = $this->modelClass; new $class()` 而非 `new ($this->modelClass)()`
