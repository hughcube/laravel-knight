<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/5
 * Time: 2:50 下午.
 */

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;

/**
 * @property int $id
 * @property string $nickname
 * @property int $sort
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 * @property null|Carbon $deleted_at
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'nickname',
        'sort',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $hidden = [];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 获取缓存.
     *
     * @return CacheInterface
     */
    public function getCache(): CacheInterface
    {
        return Cache::store('array');
    }

    public function getCacheTtl(?int $duration = null): int
    {
        return 10;
    }

    public function getCachePlaceholder(): ?string
    {
        return md5(__METHOD__);
    }

    public function onChangeRefreshCacheKeys(): array
    {
        return [
            [$this->getKeyName() => $this->getKey()],
            ['nickname' => $this->nickname],
        ];
    }
}
