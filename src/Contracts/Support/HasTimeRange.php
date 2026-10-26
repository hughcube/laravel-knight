<?php

namespace HughCube\Laravel\Knight\Contracts\Support;

use Illuminate\Support\Carbon;

/**
 * 包含时间范围的接口.
 *
 * 用于标识对象具有开始时间和结束时间
 */
interface HasTimeRange
{
    /**
     * 获取开始时间.
     *
     * @return Carbon|null
     */
    public function getStartedAt();

    /**
     * 获取结束时间.
     *
     * @return Carbon|null
     */
    public function getEndedAt();

    /**
     * 是否已开始.
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isStarted($now = null);

    /**
     * 是否已结束
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isEnded($now = null);

    /**
     * 是否在有效时间内（已开始且未结束）.
     *
     * @param  Carbon|null  $now
     * @return bool
     */
    public function isInProgress($now = null);

    /**
     * 距离开始还有多少秒，已开始返回0.
     *
     * @param  Carbon|null  $now
     * @return int
     */
    public function getStartRemaining($now = null);

    /**
     * 距离结束还有多少秒，已结束返回0.
     *
     * @param  Carbon|null  $now
     * @return int
     */
    public function getEndRemaining($now = null);
}
