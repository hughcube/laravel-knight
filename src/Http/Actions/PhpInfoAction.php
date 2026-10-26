<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 21:36.
 */

namespace HughCube\Laravel\Knight\Http\Actions;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PhpInfoAction extends Controller
{
    protected function action(): Response
    {
        ob_start();
        phpinfo();
        $content = ob_get_clean();

        if (! Str::startsWith($content, '<')) {
            $content = sprintf('<html><pre>%s</pre></html>', $content);
        }

        return new Response($content);
    }
}
