<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/6
 * Time: 8:29 下午.
 */

namespace HughCube\Laravel\Knight\Database\Eloquent;

use HughCube\Laravel\Knight\Database\Eloquent\Builder as KnightEloquentBuilder;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\Builder as KnightBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Class Builder.
 *
 * @method EloquentCollection get()
 * @method Model getModel()
 * @method KnightEloquentBuilder kCanUsable()
 * @method KnightEloquentBuilder available()
 * @method KnightEloquentBuilder sort()
 * @method KnightEloquentBuilder sortAvailable()
 * @method Connection getConnection()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 * /
 */
class Builder extends \Illuminate\Database\Eloquent\Builder
{
    use KnightBuilder;
}
