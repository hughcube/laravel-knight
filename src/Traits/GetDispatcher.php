<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/5/2
 * Time: 23:20.
 */

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Contracts\Events\Dispatcher;

/**
 * @deprecated
 * @see Container
 */
trait GetDispatcher
{
    /**
     * @return Dispatcher
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getDispatcher(): Dispatcher
    {
        /** @see Container */
        if (method_exists($this, 'getContainer')) {
            return $this->getContainer()->make(Dispatcher::class);
        }

        return app()->make(Dispatcher::class);
    }
}
