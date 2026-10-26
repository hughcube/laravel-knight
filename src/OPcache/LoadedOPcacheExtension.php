<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 22:48.
 */

namespace HughCube\Laravel\Knight\OPcache;

use Exception;

trait LoadedOPcacheExtension
{
    /**
     * @throws Exception
     */
    protected function loadedOPcacheExtension()
    {
        if (! extension_loaded('Zend OPcache')) {
            throw new Exception(
                'You do not have the Zend OPcache extension loaded, sample data is being shown instead.'
            );
        }
    }
}
