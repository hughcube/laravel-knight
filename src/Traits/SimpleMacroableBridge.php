<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 14:51.
 */

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Support\Traits\Macroable;

/**
 * @deprecated 已经弃用
 * @see Macroable
 *
 * @phpstan-ignore-next-line
 */
trait SimpleMacroableBridge
{
    public static function mixin(string $target, bool $replace = true)
    {
        foreach (static::getMacros() as $method) {
            if ($replace || ! $target::hasMacro($method)) {
                $target::macro($method, static::{$method}());
            }
        }
    }

    protected static function getMacros(): array
    {
        return [];
    }
}
