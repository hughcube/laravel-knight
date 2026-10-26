<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/4/3
 * Time: 22:21.
 */

namespace HughCube\Laravel\Knight\Traits;

trait BuildUniqueKey
{
    /**
     * @param  mixed  $data
     * @param  int|null  $length
     * @return string
     */
    protected static function buildUniqueKey($data, ?int $length = null): string
    {
        $string = is_string($data) ? $data : serialize($data);

        $crc32 = base_convert(abs(crc32($string)), 10, 36);
        $hash = sprintf('%s%s', $crc32, md5($string));

        return null === $length ? $hash : substr($hash, 0, $length);
    }

    protected static function buildUniqueCacheKey(string $prefix, $data, ?int $length = null): string
    {
        return sprintf('%s_%s', $prefix, static::buildUniqueKey($data, $length));
    }
}
