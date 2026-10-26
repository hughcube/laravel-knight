<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/4
 * Time: 7:36 下午.
 */

namespace HughCube\Laravel\Knight\Tests\Routing;

class ActionWithMiddleware
{
    use \HughCube\Laravel\Knight\Routing\Action;

    /**
     * @var array
     */
    public static $middlewareExecutionLog = [];

    /**
     * @var array
     */
    protected $middlewares = [];

    /**
     * @param  array  $middlewares
     * @return $this
     */
    public function setMiddlewares(array $middlewares)
    {
        $this->middlewares = $middlewares;

        return $this;
    }

    /**
     * @return array
     */
    protected function actionMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return array
     */
    protected function action(): array
    {
        static::$middlewareExecutionLog[] = 'action';

        return $this->p()->all();
    }

    public function rules(): array
    {
        return [
            'uuid' => 'string',
        ];
    }

    public static function resetExecutionLog()
    {
        static::$middlewareExecutionLog = [];
    }
}
