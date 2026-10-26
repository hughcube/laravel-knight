<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 14:51.
 */

namespace HughCube\Laravel\Knight\Support;

use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * @method static static|false createFromFormat(string $format, string $time, string|DateTimeZone $timezone = null)
 *
 * @deprecated
 */
class Carbon extends \Illuminate\Support\Carbon
{
    /**
     * @param  DateTimeInterface|int|float|string  $date
     * @param  string|null  $format
     * @return static|null
     */
    public static function fromDate($date, ?string $format = null): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return static::instance($date);
        }

        if (is_numeric($date) && empty($format)) {
            return static::createFromTimestamp($date);
        }

        try {
            $dateTime = static::createFromFormat($format, $date);
            /** @phpstan-ignore-next-line */
        } catch (InvalidArgumentException $e) {
            $dateTime = false;
        }

        if ($dateTime instanceof static) {
            return $dateTime;
        }

        return static::parse($date);
    }

    /**
     * @param  DateTimeInterface|int|float  $value
     * @param  string  $format
     * @return string|null
     */
    public static function asDate($value, string $format = 'Y-m-d H:i:s'): ?string
    {
        $dateTime = static::fromDate($value);

        return $dateTime instanceof static ? $dateTime->format($format) : null;
    }

    /**
     * @param  mixed  $date
     * @param  string  $format
     * @return bool
     */
    public static function isPastDate($date, string $format = 'Y-m-d H:i:s'): bool
    {
        $dateTime = static::fromDate($date, $format);

        return $dateTime instanceof static && $dateTime->isPast();
    }

    /**
     * @param  mixed  $timestamp
     * @return bool
     */
    public static function isPastTimestamp($timestamp): bool
    {
        return is_numeric($timestamp) && $timestamp <= time();
    }

    /**
     * @param  string  $date
     * @param  bool  $extended
     * @return static|false
     */
    public static function createFromRfc3339(string $date, bool $extended = false)
    {
        $format = $extended ? static::RFC3339_EXTENDED : static::RFC3339;

        return static::createFromFormat($format, $date);
    }

    /**
     * @param  string  $date
     * @return static|false
     */
    public static function createFromRfc3339Extended(string $date)
    {
        return static::createFromRfc3339($date, true);
    }

    /**
     * @return string
     */
    public function toRfc3339ExtendedString(): string
    {
        return $this->toRfc3339String(true);
    }
}
