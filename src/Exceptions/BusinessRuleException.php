<?php

namespace HughCube\Laravel\Knight\Exceptions;

use Throwable;

/**
 * Service 层业务规则异常.
 *
 * 与 UserException 无继承关系, 不在 $dontReport 中, 所有上下文都会触发告警.
 * HTTP 请求中由中间件捕获并转为 UserException, 实现友好消息渲染.
 */
class BusinessRuleException extends \RuntimeException
{
    /**
     * @var string
     */
    protected $stringCode;

    /**
     * @param  string  $message
     * @param  int  $code
     * @param  Throwable|null  $previous
     */
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  string  $stringCode
     * @return $this
     */
    public function setStringCode($stringCode)
    {
        $this->stringCode = $stringCode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStringCode()
    {
        return $this->stringCode;
    }
}
