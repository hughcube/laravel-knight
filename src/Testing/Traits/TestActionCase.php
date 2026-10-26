<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/1/16
 * Time: 11:49.
 */

namespace HughCube\Laravel\Knight\Testing\Traits;

use Illuminate\Foundation\Testing\TestCase;
use Throwable;

/**
 * @mixin TestCase
 */
trait TestActionCase
{
    /**
     * 断言 Job 执行不抛出异常.
     *
     * @param  object  $job
     */
    protected function assertKnightHandle(object $job): void
    {
        if (! method_exists($job, 'handle')) {
            $this->fail(sprintf('Object %s does not have a handle method', get_class($job)));
        }

        if (method_exists($job, 'setLogChannel')) {
            $job->setLogChannel('stdout');
        }

        $exception = null;

        try {
            $job->handle();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, $exception ? $exception->getMessage() : '');
    }
}
