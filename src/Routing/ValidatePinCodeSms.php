<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/17
 * Time: 17:33.
 */

namespace HughCube\Laravel\Knight\Routing;

use HughCube\Laravel\Knight\Exceptions\ValidatePinCodeException;

/**
 * @mixin Action
 */
trait ValidatePinCodeSms
{
    use GetMobile;

    protected function getPinCode()
    {
        return $this->getRequest()->input('pincode');
    }

    /**
     * @param  bool  $deleteAfterSuccess
     * @return void
     *
     * @throws ValidatePinCodeException
     */
    protected function validatePinCode(bool $deleteAfterSuccess = true)
    {
        $mobile = $this->getMobile();
        $iddCode = $this->getIDDCode();
        $pincode = $this->getPinCode();

        $ok = false;
        if (! empty($mobile) && ! empty($iddCode) && ! empty($pincode) && $this->checkMobile($mobile, $iddCode)) {
            $ok = $this->runValidatePinCode($mobile, $iddCode, $pincode, $deleteAfterSuccess);
        }

        if (! $ok) {
            throw new ValidatePinCodeException('请输入正确的验证码!');
        }
    }

    abstract protected function runValidatePinCode($mobile, $iddCode, $pincode, $deleteAfterSuccess): bool;
}
