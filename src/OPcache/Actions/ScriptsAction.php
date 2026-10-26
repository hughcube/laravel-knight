<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/15
 * Time: 8:42 下午.
 */

namespace HughCube\Laravel\Knight\OPcache\Actions;

use Exception;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Routing\Controller;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ScriptsAction extends Controller
{
    use LoadedOPcacheExtension;

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    protected function action(): Response
    {
        $scripts = OPcache::i()->getScripts();

        return $this->asSuccess([
            'count' => count($scripts),
            'scripts' => array_keys($scripts),
        ]);
    }
}
