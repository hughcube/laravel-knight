<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/17
 * Time: 17:33.
 */

namespace HughCube\Laravel\Knight\Routing;

use HughCube\Laravel\Knight\Exceptions\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Action
 */
trait PinCodeSms
{
    use GetMobile;

    /**
     * @return mixed|void
     *
     * @throws UserException
     */
    protected function action()
    {
        $mobile = $this->getMobile();
        $iddCode = $this->getIDDCode();

        if (empty($mobile) || empty($iddCode)) {
            return $this->emptyMobileResponse();
        }

        if (! $this->checkMobile($mobile, $iddCode)) {
            return $this->invalidMobileResponse();
        }

        $pinCode = $this->getPinCode($mobile, $iddCode);

        if ($this->enableSend()) {
            $this->send($mobile, $iddCode, $pinCode);
        }

        return $this->makeResponse();
    }

    protected function enableSend(): bool
    {
        return true;
    }

    /**
     * @param  int|string  $mobile
     * @param  int|string  $iddCode
     * @return string
     */
    abstract protected function getPinCode($mobile, $iddCode): string;

    /**
     * @param  int|string  $mobile
     * @param  int|string  $iddCode
     * @param  mixed  $pinCode
     * @return void
     */
    abstract protected function send($mobile, $iddCode, $pinCode): void;

    protected function makeResponse(): Response
    {
        return $this->asSuccess();
    }
}
