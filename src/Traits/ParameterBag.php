<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/17
 * Time: 22:21.
 */

namespace HughCube\Laravel\Knight\Traits;

use HughCube\Laravel\Knight\Support\ParameterBag as BaseParameterBag;

trait ParameterBag
{
    /**
     * @var BaseParameterBag|null
     */
    private $parameterBag = null;

    /**
     * @return void
     */
    abstract protected function loadParameters();

    /**
     * @param  string|int  $key
     * @param  mixed  $default
     * @return BaseParameterBag|mixed
     */
    protected function p($key = null, $default = null)
    {
        $this->loadParameters();

        if (null === $key) {
            return $this->parameterBag;
        }

        return $this->parameterBag->get($key, $default);
    }
}
