<?php

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Support\Carbon;

/**
 * 时间范围判断 trait.
 *
 * 提供基于开始时间和结束时间的状态判断方法
 * 需要实现 HasTimeRange 接口的 getStartedAt() 和 getEndedAt() 方法
 *
 * @see \HughCube\Laravel\Knight\Contracts\Support\HasTimeRange
 */
trait HasTimeRangeTrait
{
    /**
     * 是否已开始.
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isStarted($now = null)
    {
        $now = $now ?: Carbon::now();

        return null === $this->getStartedAt() || $this->getStartedAt() <= $now;
    }

    /**
     * 是否已结束
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isEnded($now = null)
    {
        $now = $now ?: Carbon::now();

        return null !== $this->getEndedAt() && $this->getEndedAt() <= $now;
    }

    /**
     * 是否在有效时间内（已开始且未结束）.
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isInProgress($now = null)
    {
        $now = $now ?: Carbon::now();

        return $this->isStarted($now) && ! $this->isEnded($now);
    }

    /**
     * 距离开始还有多少秒，已开始返回0.
     *
     * @param  Carbon|null  $now
     * @return int
     */
    public function getStartRemaining($now = null)
    {
        $now = $now ?: Carbon::now();
        if ($this->isStarted($now) || null === $this->getStartedAt()) {
            return 0;
        }

        return (int) $now->diffInSeconds($this->getStartedAt());
    }

    /**
     * 距离结束还有多少秒，已结束返回0.
     *
     * @param  Carbon|null  $now
     * @return int
     */
    public function getEndRemaining($now = null)
    {
        $now = $now ?: Carbon::now();
        if ($this->isEnded($now) || null === $this->getEndedAt()) {
            return 0;
        }

        return (int) $now->diffInSeconds($this->getEndedAt());
    }
}
