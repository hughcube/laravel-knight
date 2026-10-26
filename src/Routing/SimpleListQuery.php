<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/17
 * Time: 17:33.
 */

namespace HughCube\Laravel\Knight\Routing;

/**
 * @mixin Action
 *
 * @deprecated Will be removed in a future version.
 * @see ListQuery
 */
trait SimpleListQuery
{
    use ListQuery;

    /**
     * @param  mixed  $results
     * @return mixed
     */
    protected function createResponse($results)
    {
        return $this->asResponse($results);
    }
}
