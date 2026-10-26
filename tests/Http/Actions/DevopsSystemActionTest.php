<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 21:36.
 */

namespace HughCube\Laravel\Knight\Tests\Http\Actions;

use HughCube\Laravel\Knight\Http\Actions\DevopsSystemAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;

class DevopsSystemActionTest extends TestCase
{
    /**
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function testRun()
    {
        /** @var DevopsSystemAction $action */
        $action = $this->app->make(DevopsSystemAction::class);

        $this->assertInstanceOf(JsonResponse::class, $action());
    }
}
