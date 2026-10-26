<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class HealthcheckAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        return new Response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
