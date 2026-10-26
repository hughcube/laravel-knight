<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 21:36.
 */

namespace HughCube\Laravel\Knight\Http\Actions;

use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class NowAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        $now = Carbon::now();

        return $this->asSuccess([
            /** @phpstan-ignore-next-line */
            'timestamp' => $now->getTimestampAsFloat(),
            /** @phpstan-ignore-next-line */
            'rfc3339' => $now->toRfc3339ExtendedString(),
        ]);
    }
}
