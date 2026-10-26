<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/17
 * Time: 17:33.
 */

namespace HughCube\Laravel\Knight\Routing;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * @mixin Action
 */
trait ListQuery
{
    use PaginateQuery;

    protected function rules(): array
    {
        return [];
    }

    /**
     * @return int|null
     */
    protected function getPage(): ?int
    {
        return null;
    }

    /**
     * @param  EloquentBuilder|mixed  $query
     * @return null|int
     */
    protected function queryCount($query): ?int
    {
        return null;
    }

    /**
     * @return EloquentBuilder|QueryBuilder|null
     */
    abstract protected function makeQuery(): ?object;
}
