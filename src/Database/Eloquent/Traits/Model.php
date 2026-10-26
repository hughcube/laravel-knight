<?php

namespace HughCube\Laravel\Knight\Database\Eloquent\Traits;

use DateTimeInterface;
use HughCube\Laravel\Knight\Database\Eloquent\Builder;
use HughCube\Laravel\Knight\Support\Json;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\Str;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * Trait QueryCache.
 *
 * @method static Builder query()
 * @method static Builder newQuery()
 * @method static Builder kCanUsable()
 * @method static Builder available()
 * @method static Builder sort()
 * @method static Builder sortAvailable()
 *
 * @mixin SoftDeletes
 * @mixin EloquentModel
 */
trait Model
{
    /**
     * @var bool
     */
    private bool $isFromCache = false;

    /**
     * @var array
     */
    protected static array $modelCacheKeyMakeCache = [];

    /**
     * 缓存每个 Model 子类的 setKeysForSaveQueryFrom{TraitName} 方法列表.
     *
     * @var array<string, string[]>
     */
    protected static array $setKeysForSaveQueryMethods = [];

    /**
     * 缓存每个 Model 子类的 setKeysForSelectQueryFrom{TraitName} 方法列表.
     *
     * @var array<string, string[]>
     */
    protected static array $setKeysForSelectQueryMethods = [];

    /**
     * 统一入口：自动调用所有 trait 的 setKeysForSaveQueryFrom{TraitName}() 方法.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $query = parent::setKeysForSaveQuery($query);

        if (! isset(static::$setKeysForSaveQueryMethods[static::class])) {
            static::$setKeysForSaveQueryMethods[static::class] = Collection::make(class_uses_recursive($this))
                ->map(fn ($trait) => 'setKeysForSaveQueryFrom'.class_basename($trait))
                ->filter(fn ($method) => method_exists($this, $method))
                ->values()->all();
        }

        foreach (static::$setKeysForSaveQueryMethods[static::class] as $method) {
            $query = $this->{$method}($query);
        }

        return $query;
    }

    /**
     * 统一入口：自动调用所有 trait 的 setKeysForSelectQueryFrom{TraitName}() 方法.
     *
     * 影响 refresh()、fresh() 等 SELECT 操作，确保分区表查询带上分区键。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSelectQuery($query)
    {
        $query = parent::setKeysForSelectQuery($query);

        if (! isset(static::$setKeysForSelectQueryMethods[static::class])) {
            static::$setKeysForSelectQueryMethods[static::class] = Collection::make(class_uses_recursive($this))
                ->map(fn ($trait) => 'setKeysForSelectQueryFrom'.class_basename($trait))
                ->filter(fn ($method) => method_exists($this, $method))
                ->values()->all();
        }

        foreach (static::$setKeysForSelectQueryMethods[static::class] as $method) {
            $query = $this->{$method}($query);
        }

        return $query;
    }

    /**
     * @param  DateTimeInterface|int|float|string|null  $date
     * @param  string|null  $format
     * @return Carbon|null
     */
    protected function toDateTime($date = null, ?string $format = null): ?Carbon
    {
        $format = $format ?? $this->getDateFormat();

        if (empty($date)) {
            return null;
        }

        if (empty($format)) {
            /** @phpstan-ignore-next-line */
            return Carbon::tryParse($date);
        }

        return Carbon::createFromFormat($format, $date) ?: null;
    }

    /**
     * @param  DateTimeInterface|int|float|null  $dateTime
     * @param  string  $format
     * @return string|null
     */
    protected function formatDateTime($dateTime, string $format = 'Y-m-d H:i:s'): ?string
    {
        $dateTime = $this->toDateTime($dateTime);

        return $dateTime instanceof DateTimeInterface ? $dateTime->format($format) : null;
    }

    /**
     * @param  mixed  $date
     * @return null|Carbon
     */
    public function getCreatedAtAttribute($date)
    {
        return $this->toDateTime($date);
    }

    /**
     * @param  mixed  $date
     * @return null|Carbon
     */
    public function getUpdatedAtAttribute($date)
    {
        return $this->toDateTime($date);
    }

    /**
     * @param  mixed  $date
     * @return null|Carbon
     */
    public function getDeletedAtAttribute($date)
    {
        return $this->toDateTime($date);
    }

    public function formatDateColumn($name, string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->formatDateTime($this->{$name}, $format);
    }

    public function formatCreatedAt(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->formatDateColumn($this->getCreatedAtColumn(), $format);
    }

    public function formatUpdatedAt(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->formatDateColumn($this->getUpdatedAtColumn(), $format);
    }

    public function formatDeleteAt(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->formatDateColumn($this->getDeletedAtColumn(), $format);
    }

    public function getSetColumnCollection($name, $separator = ',', $filter = null): IlluminateCollection
    {
        $values = Arr::wrap(explode($separator, $this->{$name}));

        return IlluminateCollection::make($values)->filter($filter)->unique()->values();
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        $deletedAt = $this->getAttribute($this->getDeletedAtColumn());

        if (null === $deletedAt) {
            return false;
        }

        if (is_numeric($deletedAt) && 0 == $deletedAt) {
            return false;
        }

        return true;
    }

    public function getDeletedAtColumn()
    {
        return defined('static::DELETED_AT') ? constant('static::DELETED_AT') : 'deleted_at';
    }

    public function genDefaultSort(): int
    {
        return Carbon::now()->getTimestamp() - 1660899108;
    }

    public static function genModelVersion(): int
    {
        return abs(crc32(serialize([Str::random(100), microtime()])));
    }

    /**
     * @return $this
     */
    public function resetModelVersion()
    {
        /** @phpstan-ignore-next-line */
        $this->data_version = $this->genModelVersion();

        return $this;
    }

    /**
     * @param  $query
     * @return Builder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }

    public static function noCacheQuery(): Builder
    {
        return static::query()->noCache();
    }

    /**
     * @deprecated 请使用 scope 方式: Model::query()->available() 或 Model::available()
     */
    public static function availableQuery(): Builder
    {
        return static::query()->available();
    }

    /**
     * @deprecated 请使用 scope 方式: Model::query()->sort() 或 Model::sort()
     */
    public static function sortQuery(): Builder
    {
        return static::query()->sort();
    }

    /**
     * @deprecated 请使用 scope 方式: Model::query()->sortAvailable() 或 Model::sortAvailable()
     */
    public static function sortAvailableQuery(): Builder
    {
        return static::query()->sortAvailable();
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeAvailable($query)
    {
        return $query->whereDeletedAtColumn();
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeSort($query)
    {
        return $query->orderByDesc('sort')->orderByDesc('id');
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeSortAvailable($query)
    {
        return $query->available()->sort();
    }

    public function getCache(): ?CacheInterface
    {
        return null;
    }

    public function getCachePlaceholder(): ?string
    {
        return null;
    }

    public function hasCachePlaceholder(): bool
    {
        return null !== $this->getCachePlaceholder();
    }

    public function isCachePlaceholder($value): bool
    {
        return $value === $this->getCachePlaceholder() && $this->hasCachePlaceholder();
    }

    public function getModelCachePrefix(): string
    {
        return 'm1';
    }

    public function getCacheVersion(): string
    {
        return 'v1';
    }

    /**
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    public function getCacheTtl(?int $duration = null): int
    {
        return null === $duration ? random_int(5 * 24 * 3600, 7 * 24 * 3600) : $duration;
    }

    public function isFromCache(): bool
    {
        return $this->isFromCache;
    }

    /**
     * @return $this
     */
    public function setIsFromCache($is = true)
    {
        $this->isFromCache = $is;

        return $this;
    }

    public function onChangeRefreshCacheKeys(): array
    {
        return [
            [$this->getKeyName() => $this->getKey()],
        ];
    }

    /**
     * @deprecated static::deleteRowCache()
     */
    public function refreshRowCache(): bool
    {
        return $this->deleteRowCache();
    }

    /**
     * Called when model data has changed (created/updated/deleted).
     * Default: clear row cache. Override to add sync logic (e.g. OTS).
     */
    public function onKnightModelChanged(): void
    {
        $this->deleteRowCache();
    }

    /**
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    public function deleteRowCache(): bool
    {
        $cacheKeys = Collection::make($this->onChangeRefreshCacheKeys())
            ->mapWithKeys(function ($id, $key) {
                return [$key => $this->makeColumnsCacheKey($id)];
            });

        return $this->newQuery()->getCache()->deleteMultiple($cacheKeys->values());
    }

    /**
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    public function resetRowCache(): bool
    {
        $cacheKeys = Collection::make($this->onChangeRefreshCacheKeys())
            ->mapWithKeys(function ($id, $key) {
                return [$this->makeColumnsCacheKey($id) => $this];
            });

        return $this->newQuery()->getCache()->setMultiple($cacheKeys, $this->getCacheTtl());
    }

    /**
     * make字段的缓存key.
     */
    public function makeColumnsCacheKey(array $columns): string
    {
        $class = get_class($this);

        $cacheKey = [];
        foreach ($columns as $name => $value) {
            $name = is_numeric($name) ? $this->getKeyName() : $name;

            /** Builds the property value of the cache key */
            if (! isset(static::$modelCacheKeyMakeCache['getMakeCacheKeyColumnValue'][$class][$name])) {
                static::$modelCacheKeyMakeCache['getMakeCacheKeyColumnValue'][$class][$name] = sprintf('getMakeCacheKey%sValue', Str::studly($name));
            }
            $method = static::$modelCacheKeyMakeCache['getMakeCacheKeyColumnValue'][$class][$name];

            $cacheKey[$name] = method_exists($this, $method) ? $this->{$method}($name) : strval($value);
        }

        ksort($cacheKey);
        $cacheKey = json_encode($cacheKey);

        if (! isset(static::$modelCacheKeyMakeCache['classCacheKeyPrefix'][$class])) {
            static::$modelCacheKeyMakeCache['classCacheKeyPrefix'][$class] = sprintf(
                '%s-%s',
                Str::snake(Str::afterLast($class, '\\')),
                base_convert((string) abs(crc32($class)), 10, 36)
            );
        }

        return sprintf(
            '%s:%s:%s:%s-%s',
            $this->getModelCachePrefix(),
            static::$modelCacheKeyMakeCache['classCacheKeyPrefix'][$class],
            $this->getCacheVersion(),
            md5($string = sprintf('%s:%s', $class, $cacheKey)),
            base_convert((string) abs(crc32($string)), 10, 32)
        );
    }

    /**
     * @param  mixed  $id
     * @return null|static
     */
    public static function findById($id)
    {
        return static::findByIds([$id])->first();
    }

    /**
     * @param  array|Arrayable|Traversable  $ids
     * @return EloquentCollection<int, static>
     */
    public static function findByIds($ids): EloquentCollection
    {
        return static::query()->findByPks($ids);
    }

    /**
     * Is a primary key value.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function isMatchPk($value): bool
    {
        return ! empty($value);
    }

    /**
     * 是否可用的数据.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return ! $this->isDeleted();
    }

    /**
     * @return null|$this
     *
     * @example $model->isAvailable() ? $model : null
     *          $model->ifReturnSelf($model->isAvailable())
     * @example $model instanceof Model && $model->isAvailable() ? $model : null
     *          $model?->ifReturnSelf($model?->isAvailable())
     */
    public function ifReturnSelf($condition)
    {
        return $condition ? $this : null;
    }

    /**
     * @return null|$this
     *
     * @example $model->isAvailable() ? $model : null
     *          $model->ifAvailableReturnSelf()
     * @example $model instanceof Model && $model->isAvailable() ? $model : null
     *          $model?->ifAvailableReturnSelf()
     */
    public function ifAvailableReturnSelf()
    {
        return $this->ifReturnSelf($this->isAvailable());
    }

    /**
     * @return static
     */
    public function clone(?callable $callable = null)
    {
        $model = clone $this;

        if (is_callable($callable)) {
            $callable($model);
        }

        return $model;
    }

    /** @phpstan-ignore-next-line */
    public function newCollection(array $models = []): EloquentCollection
    {
        return new EloquentCollection($models);
    }

    public static function isAvailableModel($model): bool
    {
        return $model instanceof self && $model->isAvailable();
    }

    protected function json2Array($value, $filter = false): array
    {
        $collection = Collection::make();

        if (empty($value)) {
            $collection = Collection::make();
        } elseif (is_string($value)) {
            $collection = Collection::make(Json::decodeArray($value));
        } elseif (is_array($value)) {
            $collection = Collection::make($value);
        }

        if (true === $filter) {
            $collection = $collection->filter();
        } elseif (false !== $filter) {
            $collection = $collection->filter($filter);
        }

        return $collection->all();
    }

    /**
     * 判断两个 Model 是否为同一条记录且所有属性值相同.
     *
     * 与 Laravel 的 is() 不同, is() 只判断是否同一条记录(同表同主键),
     * 本方法还会比较所有原始属性值是否完全一致.
     *
     * @param  mixed  $model
     * @return bool
     */
    public function hasSameAttributes($model): bool
    {
        if (! $model instanceof EloquentModel || ! $this->is($model)) {
            return false;
        }

        /** 主键为 null 时无法确认是同一条记录 */
        if (null === $this->getKey()) {
            return false;
        }

        $attributes = $model->getAttributes();
        $thisAttributes = $this->getAttributes();

        $names = Collection::empty()
            ->merge(array_keys($attributes))
            ->merge(array_keys($thisAttributes))
            ->unique()->values();

        foreach ($names as $name) {
            if (! array_key_exists($name, $attributes) || ! array_key_exists($name, $thisAttributes)) {
                return false;
            }

            $a = $thisAttributes[$name];
            $b = $attributes[$name];

            /** 统一日期时间为字符串, 避免对象引用比较 */
            $a = $a instanceof DateTimeInterface ? $a->format('Y-m-d H:i:s') : $a;
            $b = $b instanceof DateTimeInterface ? $b->format('Y-m-d H:i:s') : $b;

            /** int vs numeric-string 用字符串比较 (如 PDO 返回 "1" vs int 1), 其他用 === 严格比较 */
            if ((is_int($a) || is_int($b)) && (! is_int($a) || ! is_int($b)) && (is_numeric($a) && is_numeric($b))) {
                if (strval($a) !== strval($b)) {
                    return false;
                }
            } elseif ($a !== $b) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $model
     * @return bool
     *
     * @deprecated 请使用 hasSameAttributes()
     */
    public function equal($model): bool
    {
        return $this->hasSameAttributes($model);
    }

    public function scopeKCanUsable($query)
    {
        return $query;
    }

    public function isKCanUsable(): bool
    {
        return $this->isAvailable();
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    protected function convertEmptyStringsToNull($value)
    {
        return ('' === $value || null === $value) ? null : $value;
    }
}
