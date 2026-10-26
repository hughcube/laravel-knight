<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/12
 * Time: 21:22.
 */

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

trait MultipleHandler
{
    use Container;

    protected function isStopMultipleHandlerResult($result, ?Throwable $exception = null): bool
    {
        //return null !== $result && !$exception instanceof Throwable;
        return false;
    }

    /**
     * @throws Throwable
     */
    protected function throwMultipleHandlerException(Throwable $exception): void
    {
        throw $exception;
    }

    /**
     * @throws Throwable
     */
    protected function reportMultipleHandlerException(Throwable $exception): void
    {
        $this->getExceptionHandler()->report($exception);
    }

    protected function isSkipMultipleHandlerMethod(ReflectionMethod $method): bool
    {
        return false;
    }

    /**
     * @return Collection<int, array{
     *     method: ReflectionMethod,
     *     result: mixed,
     *     exception: Throwable|null
     * }>
     *
     * @throws Throwable
     */
    protected function triggerMultipleHandlers(bool $tryException = true): Collection
    {
        $results = Collection::empty();

        foreach ($this->getMultipleHandlers() as $method) {
            $result = $exception = null;

            try {
                $result = $this->{$method->name}();
            } catch (Throwable $exception) {
            }
            $results->add(['method' => $method, 'result' => $result, 'exception' => $exception]);

            /** 记录异常或者抛出 */
            if ($exception instanceof Throwable && $tryException) {
                $this->reportMultipleHandlerException($exception);
            } elseif ($exception instanceof Throwable) {
                $this->throwMultipleHandlerException($exception);
            }

            /** 是否终止执行 */
            if ($this->isStopMultipleHandlerResult($result, $exception)) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<int, ReflectionMethod>
     */
    protected function getMultipleHandlers(): array
    {
        $handlers = [];

        $reflection = new ReflectionClass($this);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            /** 方法名必须包含Handler, 不区分大小写 */
            if (false === ($position = strripos($method->name, 'Handler'))) {
                continue;
            }

            /** 跳过指定方法不执行 */
            if ('getExceptionHandler' === $method->name || $this->isSkipMultipleHandlerMethod($method)) {
                continue;
            }

            /** 获取sort属性 */
            $sort = substr($method->name, $position + strlen('Handler'));
            if ('' !== $sort && ! ctype_digit($sort)) {
                continue;
            }

            $handlers[] = ['method' => $method, 'sort' => intval($sort)];
        }

        /** 排序 */
        usort($handlers, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        /**
         * 返回所有的method对象
         *
         * @phpstan-ignore-next-line
         */
        return array_values(array_map(function ($handler) {
            return $handler['method'];
        }, $handlers));
    }
}
