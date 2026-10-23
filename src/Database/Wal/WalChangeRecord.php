<?php

namespace HughCube\Laravel\Knight\Database\Wal;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonSerializable;

class WalChangeRecord implements JsonSerializable
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
     * - INSERT: 始终为空数组.
     *
     * @var array<string, mixed>
     */
    private $oldColumns;

    /** @var class-string<Model> Model 类名（如 App\Models\Question） */
    private $modelClass;

    /**
     * 运行时上下文，用于 Listener 之间传递数据。
     * 不参与序列化——Job dispatch 时 record 已序列化，context 是事件分发过程中的运行时状态。
     *
     * @var array<string, mixed>
     */
    private $context = [];

    /**
     * 自定义 Model 查询回调，用于覆盖 fetchModel 的默认查询逻辑。
     * 不参与序列化——与 context 相同，属于运行时状态。
     *
     * @var callable|null 签名: function(WalChangeRecord): Model|null
     */
    private $fetcher;

    /**
     * @param string      $kind
     * @param string      $table
     * @param string|null $partitionTable
     * @param int|string  $id
     * @param string      $keyName
     * @param array       $columns
     * @param array       $oldColumns
     * @param string      $modelClass
     */
    public function __construct($kind, $table, $partitionTable, $id, $keyName, array $columns, array $oldColumns, $modelClass)
    {
        if (!in_array($kind, [self::KIND_INSERT, self::KIND_UPDATE, self::KIND_DELETE], true)) {
            throw new InvalidArgumentException(sprintf('Invalid WAL change kind: %s', $kind));
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

    /**
     * 从 wal2json change 数组构建 WalChangeRecord。
     *
     * 自动兼容 format-version 1 和 format-version 2：
     * - v1: kind + columnnames/columnvalues 并行数组 + oldkeys.keynames/keyvalues
     * - v2: action + columns[{name,type,value}] + identity[{name,type,value}]
     *
     * @param array       $change        wal2json 单条 change 记录
     * @param string      $resolvedTable 解析后的父表名
     * @param string|null $rawTable      原始表名（分区子表），与 resolvedTable 相同时传 null
     * @param string      $keyName       主键列名
     * @param string      $modelClass    Model 类名
     *
     * @return self|null 无法提取主键时返回 null
     */
    public static function fromWal2json(array $change, $resolvedTable, $rawTable, $keyName, $modelClass)
    {
        /** format-version 2 使用 action 字段 */
        if (isset($change['action'])) {
            return self::fromWal2jsonV2($change, $resolvedTable, $rawTable, $keyName, $modelClass);
        }

        $kind = $change['kind'] ?? null;
        if (null === $kind) {
            return null;
        }

        $columns = self::combineKeyValues(
            $change['columnnames'] ?? [],
            $change['columnvalues'] ?? []
        );
        $oldColumns = self::combineKeyValues(
            $change['oldkeys']['keynames'] ?? [],
            $change['oldkeys']['keyvalues'] ?? []
        );

        /** DELETE 从 oldColumns 取主键，INSERT/UPDATE 从 columns 取 */
        $id = ('delete' === $kind)
            ? ($oldColumns[$keyName] ?? null)
            : ($columns[$keyName] ?? null);

        if (null === $id) {
            return null;
        }

        $partitionTable = ($rawTable !== $resolvedTable) ? $rawTable : null;

        return new self($kind, $resolvedTable, $partitionTable, $id, $keyName, $columns, $oldColumns, $modelClass);
    }

    /**
     * 从 wal2json format-version 2 的 change 数组构建 WalChangeRecord。
     *
     * v2 格式：
     * - action: I(insert)/U(update)/D(delete)/T(truncate)
     * - columns: [{name, type, value}, ...] （INSERT/UPDATE 的新值）
     * - identity: [{name, type, value}, ...] （UPDATE/DELETE 的旧值/主键）
     *
     * @param array       $change        wal2json v2 单条 change 记录
     * @param string      $resolvedTable 解析后的父表名
     * @param string|null $rawTable      原始表名
     * @param string      $keyName       主键列名
     * @param string      $modelClass    Model 类名
     *
     * @return self|null
     */
    protected static function fromWal2jsonV2(array $change, $resolvedTable, $rawTable, $keyName, $modelClass)
    {
        /** action → kind 映射 */
        $actionMap = ['I' => self::KIND_INSERT, 'U' => self::KIND_UPDATE, 'D' => self::KIND_DELETE];
        $kind = $actionMap[$change['action']] ?? null;
        if (null === $kind) {
            return null;
        }

        $columns = self::flattenV2Columns($change['columns'] ?? []);
        $oldColumns = self::flattenV2Columns($change['identity'] ?? []);

        /** DELETE 从 oldColumns 取主键，INSERT/UPDATE 从 columns 取 */
        $id = (self::KIND_DELETE === $kind)
            ? ($oldColumns[$keyName] ?? null)
            : ($columns[$keyName] ?? null);

        if (null === $id) {
            return null;
        }

        $partitionTable = ($rawTable !== $resolvedTable) ? $rawTable : null;

        return new self($kind, $resolvedTable, $partitionTable, $id, $keyName, $columns, $oldColumns, $modelClass);
    }

    /**
     * wal2json v2 中未变更 TOAST 列的哨兵值。
     *
     * UPDATE 时未变更的 TOAST 列在 v2 格式下 value 为此字符串，
     * 而非实际值或 null。下游需通过 isToastUnchanged() 检测。
     */
    const TOAST_UNCHANGED = 'unchanged-toast-datum';

    /** fetchModel 内部缓存 key */
    const FETCH_MODEL_CACHE_KEY = '__kr_fm_9d7e4a1b3f6c82e5';

    /**
     * 将 format-version 2 的 columns 数组扁平化为 [name => value] 关联数组。
     *
     * v2 输入: [{"name":"id","type":"integer","value":1}, {"name":"title","type":"text","value":"hello"}]
     * 输出:    ["id" => 1, "title" => "hello"]
     *
     * TOAST 列哨兵值 "unchanged-toast-datum" 会被直接跳过（不设 key），
     * 避免 toModel() 将哨兵字符串填充到 Model 属性中。
     * 使用 isToastUnchanged() 检测某列是否为未变更的 TOAST 列。
     *
     * @param array $v2Columns
     *
     * @return array<string, mixed>
     */
    protected static function flattenV2Columns(array $v2Columns)
    {
        $result = [];
        foreach ($v2Columns as $col) {
            if (isset($col['name'])) {
                $value = $col['value'] ?? null;
                if (self::TOAST_UNCHANGED !== $value) {
                    $result[$col['name']] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * 检查指定列是否为未变更的 TOAST 列（仅 v2 格式 UPDATE 场景有意义）。
     *
     * TOAST 列在 flattenV2Columns 中被跳过（key 不存在于 columns），
     * 因此通过 array_key_exists 即可判断。
     *
     * @param string $column
     *
     * @return bool
     */
    public function isToastUnchanged($column)
    {
        return !array_key_exists($column, $this->columns);
    }

    /**
     * 将并行的 names/values 数组组合为关联数组。长度不匹配时返回空数组。
     *
     * @param array $names
     * @param array $values
     *
     * @return array<string, mixed>
     */
    protected static function combineKeyValues(array $names, array $values)
    {
        if (empty($names) || count($names) !== count($values)) {
            return [];
        }

        return array_combine($names, $values);
    }

    /** @return string */
    public function getKind()
    {
        return $this->kind;
    }

    /** @return string */
    public function getTable()
    {
        return $this->table;
    }

    /** @return string|null */
    public function getPartitionTable()
    {
        return $this->partitionTable;
    }

    /** @return int|string */
    public function getId()
    {
        return $this->id;
    }

    /** @return string */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /** @return array<string, mixed> */
    public function getColumns()
    {
        return $this->columns;
    }

    /** @return array<string, mixed> */
    public function getOldColumns()
    {
        return $this->oldColumns;
    }

    /** @return string */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasContext($key)
    {
        return array_key_exists($key, $this->context);
    }

    /** @return bool */
    public function isInsert()
    {
        return self::KIND_INSERT === $this->kind;
    }

    /** @return bool */
    public function isUpdate()
    {
        return self::KIND_UPDATE === $this->kind;
    }

    /** @return bool */
    public function isDelete()
    {
        return self::KIND_DELETE === $this->kind;
    }

    /** @return bool */
    public function isFromPartition()
    {
        return null !== $this->partitionTable;
    }

    /**
     * 用 WAL 数据构建 Model 实例，不回查 DB。
     *
     * - INSERT/UPDATE: forceFill(columns)，包含完整新行数据
     * - DELETE: forceFill(oldColumns)，默认 REPLICA IDENTITY 下只有主键
     * - exists 标记：INSERT/UPDATE=true（记录已在 DB 中），DELETE=false
     *
     * @return Model
     */
    public function toModel()
    {
        $class = $this->modelClass;
        /** @var Model $model */
        $model = new $class();

        if ($this->isDelete()) {
            $model->forceFill($this->oldColumns);
        } else {
            $model->forceFill($this->columns);
        }

        $model->{$this->keyName} = $this->id;
        $model->syncOriginal();
        $model->exists = !$this->isDelete();

        return $model;
    }

    /**
     * 设置自定义 Model 查询回调，覆盖 fetchModel 的默认查询逻辑。
     *
     * 适用场景：需要带额外条件查询（软删除、分区键）、eager load 关联、
     * 或使用非标准查询方式获取 Model。
     *
     * @param callable $fetcher 签名: function(WalChangeRecord $record): Model|null
     *
     * @return $this
     */
    public function setFetcher(callable $fetcher)
    {
        $this->fetcher = $fetcher;

        return $this;
    }

    /**
     * 从 DB 查询完整 Model，结果缓存在 context 中供多个 Listener 复用。
     *
     * sync 模式下多个 Listener 共享同一个 WalChangeRecord 实例，
     * 第一个 Listener 触发 DB 查询，后续 Listener 直接取缓存。
     * queue 模式下反序列化后 context 为空，每个 worker 独立查询一次。
     *
     * 查询优先级：
     * 1. 自定义 fetcher（通过 setFetcher 设置）
     * 2. Model 的 findById()（走 PSR SimpleCache）
     * 3. fallback 到 Model::query()->find()
     *
     * @return Model|null DELETE 后记录已不存在时返回 null
     */
    public function fetchModel()
    {
        $key = self::FETCH_MODEL_CACHE_KEY;

        if ($this->hasContext($key)) {
            return $this->getContext($key);
        }

        if ($this->fetcher) {
            $model = call_user_func($this->fetcher, $this);
        } elseif (method_exists($this->modelClass, 'findById')) {
            $model = $this->modelClass::findById($this->id);
        } else {
            $model = $this->modelClass::query()->find($this->id);
        }

        $this->setContext($key, $model);

        return $model;
    }

    /**
     * 手动设置缓存的 Model 实例。
     *
     * 适用于 Listener 已通过自定义条件查询到 Model 的场景，
     * 后续 Listener 调用 fetchModel() 时直接复用，避免重复查询。
     * 传入 null 表示记录不存在（缓存为 null，不再重复查询）。
     *
     * @param Model|null $model
     *
     * @return $this
     */
    public function setModel($model)
    {
        $this->setContext(self::FETCH_MODEL_CACHE_KEY, $model);

        return $this;
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'kind'           => $this->kind,
            'table'          => $this->table,
            'partitionTable' => $this->partitionTable,
            'id'             => $this->id,
            'keyName'        => $this->keyName,
            'columns'        => $this->columns,
            'oldColumns'     => $this->oldColumns,
            'modelClass'     => $this->modelClass,
        ];
    }

    /**
     * 序列化时排除 context——它是运行时状态，不应跟随 Job 进入队列。
     *
     * @return array
     */
    public function __sleep()
    {
        return ['kind', 'table', 'partitionTable', 'id', 'keyName', 'columns', 'oldColumns', 'modelClass'];
    }

    /**
     * 反序列化时初始化被 __sleep 排除的运行时属性。
     *
     * PHP 的 unserialize 不调用构造函数，__sleep 排除的属性在反序列化后为 null
     * 而非类定义中的默认值，会导致 hasContext() 等方法对 null 调用 array_key_exists 报错。
     */
    public function __wakeup()
    {
        $this->context = [];
        $this->fetcher = null;
    }
}
