<?php

namespace HughCube\Laravel\Knight\Database\Eloquent\Traits;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @mixin EloquentModel
 */
trait Auditable
{
    /**
     * @var bool
     */
    protected $auditEnabled = true;

    /**
     * @var array
     */
    protected $auditChanges = [];

    protected static function bootAuditable(): void
    {
        static::updating(function ($model) {
            /** @var static $model */
            if (! $model->isAuditEnabled()) {
                return;
            }

            $changes = [];
            $dirty = $model->getDirty();
            $auditableColumns = $model->getAuditableColumns();
            $excludedColumns = $model->getAuditExcludedColumns();

            foreach ($dirty as $column => $newValue) {
                if (null !== $auditableColumns && ! in_array($column, $auditableColumns)) {
                    continue;
                }

                if (in_array($column, $excludedColumns)) {
                    continue;
                }

                $changes[] = [
                    'column' => $column,
                    'old' => $model->getOriginal($column),
                    'new' => $newValue,
                    'changed_at' => Carbon::now()->toDateTimeString(),
                ];
            }

            if (! empty($changes)) {
                $model->auditChanges = $changes;
                $model->recordAuditLog($changes);
            }
        });
    }

    /**
     * 获取需要审计的字段（默认全部，子类可覆盖）.
     *
     * @return array|null
     */
    public function getAuditableColumns(): ?array
    {
        return null;
    }

    /**
     * 获取不需要审计的字段（子类可覆盖）.
     *
     * @return array
     */
    public function getAuditExcludedColumns(): array
    {
        return [];
    }

    /**
     * 获取变更记录.
     *
     * @return array
     */
    public function getAuditChanges(): array
    {
        return $this->auditChanges;
    }

    /**
     * 启用审计
     *
     * @return $this
     */
    public function enableAudit()
    {
        $this->auditEnabled = true;

        return $this;
    }

    /**
     * 禁用审计
     *
     * @return $this
     */
    public function disableAudit()
    {
        $this->auditEnabled = false;

        return $this;
    }

    /**
     * 是否启用审计
     *
     * @return bool
     */
    public function isAuditEnabled(): bool
    {
        return $this->auditEnabled;
    }

    /**
     * 审计记录存储方式.
     *
     * @param  array  $changes
     * @return void
     */
    protected function recordAuditLog(array $changes): void
    {
        Log::info(sprintf(
            'Audit [%s:%s]: %s',
            static::class,
            $this->getKey(),
            json_encode($changes, JSON_UNESCAPED_UNICODE)
        ));
    }
}
