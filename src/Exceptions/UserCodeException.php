<?php

namespace HughCube\Laravel\Knight\Exceptions;

use Throwable;

class UserCodeException extends UserException
{
    /**
     * @var string
     */
    protected $stringCode;

    /**
     * @param  string  $code
     * @param  string  $message
     * @param  int  $intCode
     * @param  Throwable|null  $previous
     */
    public function __construct($code, $message = '', $intCode = 0, $previous = null)
    {
        $this->stringCode = $code;

        parent::__construct($message, $intCode, $previous);
    }

    /**
     * @return string
     */
    public function getStringCode(): string
    {
        return $this->stringCode ?: 'UserException';
    }
}
