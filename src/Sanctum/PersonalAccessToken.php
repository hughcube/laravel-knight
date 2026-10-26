<?php

namespace HughCube\Laravel\Knight\Sanctum;

use HughCube\Laravel\Knight\Database\Eloquent\Traits\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * Class PersonalAccessToken.
 *
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property string|array|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static static findById($id)
 */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    use Model;

    public function onChangeRefreshCacheKeys(): array
    {
        return [
            [$this->getKeyName() => $this->getKey()],
            ['token' => $this->token],
        ];
    }

    public static function getExpiresIn(): int
    {
        return 365 * 24 * 3600;
    }

    public function isValidAccessToken(bool $isValid = true): bool
    {
        if (! $this->last_used_at instanceof Carbon) {
            return true;
        }

        if (0 <= $this->getExpiresIn()) {
            return true;
        }

        return $this->last_used_at->gt(Carbon::now()->subSeconds($this->getExpiresIn()));
    }

    public function getAccessSecret(): ?string
    {
        return ($this->abilities['access_secret'] ?? null) ?: null;
    }

    /**
     * token对应的source.
     */
    public function getSource(): ?string
    {
        return ($this->abilities['source'] ?? null) ?: null;
    }

    /**
     * @return static|null
     */
    public static function findToken($token): ?PersonalAccessToken
    {
        if (! str_contains($token, '|')) {
            $token = static::query()->findUniqueRow(['token' => hash('sha256', $token)]);

            return $token instanceof static ? $token : null;
        }

        [$id, $token] = explode('|', $token, 2);

        $instance = static::findById($id);
        if ($instance instanceof self && hash_equals($instance->token, hash('sha256', $token))) {
            return $instance;
        }

        return null;
    }

    public function tokenable(): Attribute
    {
        return new Attribute(function ($value, $attributes) {
            $class = $attributes['tokenable_type'];

            return $class::findById($attributes['tokenable_id']);
        });
    }

    public function skipSave($hours = 24): bool
    {
        $dirty = $this->getDirty();

        /**
         * 在app的api情况下, 如果只是修改last_used_at属性, 24个小时内只操作一次
         *
         * @phpstan-ignore-next-line
         */
        $originalLastUsedAt = Carbon::tryParse($this->last_used_at);

        return 1 === count($dirty)
            && isset($dirty['last_used_at'])
            && $originalLastUsedAt instanceof Carbon
            && $originalLastUsedAt->clone()->addHours($hours)->isFuture();
    }

    public function save(array $options = []): bool
    {
        if ($this->skipSave()) {
            return true;
        }

        return parent::save($options);
    }
}
