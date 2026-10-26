<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/4
 * Time: 7:36 下午.
 */

namespace HughCube\Laravel\Knight\Tests\Routing;

use Closure;

class TestMiddleware
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param  string  $name
     */
    public function __construct($name = 'default')
    {
        $this->name = $name;
    }

    /**
     * @param  mixed  $action
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($action, Closure $next)
    {
        ActionWithMiddleware::$middlewareExecutionLog[] = "middleware:{$this->name}:before";

        $response = $next($action);

        ActionWithMiddleware::$middlewareExecutionLog[] = "middleware:{$this->name}:after";

        return $response;
    }
}
