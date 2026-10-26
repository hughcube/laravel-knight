<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 21:36.
 */

namespace HughCube\Laravel\Knight\Http\Actions;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class RequestShowAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        return $this->asSuccess([
            'uri' => $this->getRequest()->getUri(),
            'https' => $this->getRequest()->isSecure(),
            'method' => $this->getRequest()->getMethod(),
            'host' => $this->getRequest()->getHost(),
            'server' => $this->getRequest()->server->all(),
            'env' => $_ENV,
            'headers' => $this->getRequest()->headers->all(),
            'content' => serialize($this->getRequest()->getContent()),
        ]);
    }
}
