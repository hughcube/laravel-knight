<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PhpInfoAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        ob_start();
        phpinfo();
        $content = ob_get_clean();

        if (! Str::startsWith($content, '<')) {
            $content = sprintf('<html><pre>%s</pre></html>', $content);
        }

        return new Response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
