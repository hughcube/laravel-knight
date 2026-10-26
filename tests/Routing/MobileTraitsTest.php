<?php

namespace HughCube\Laravel\Knight\Tests\Routing;

use HughCube\Laravel\Knight\Exceptions\MobileEmptyException;
use HughCube\Laravel\Knight\Exceptions\MobileInvalidException;
use HughCube\Laravel\Knight\Exceptions\ValidatePinCodeException;
use HughCube\Laravel\Knight\Routing\Controller;
use HughCube\Laravel\Knight\Routing\GetMobile;
use HughCube\Laravel\Knight\Routing\PinCodeSms;
use HughCube\Laravel\Knight\Routing\SendPinCodeSms;
use HughCube\Laravel\Knight\Routing\ValidatePinCodeSms;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileTraitsGetMobileAction
{
    use GetMobile;

    public Request $request;

    public function getRequest(): Request
    {
        return $this->request;
    }
}

class PinCodeSmsAction extends Controller
{
    use PinCodeSms;

    public Request $request;
    public bool $sendCalled = false;
    public bool $checkMobileResult = true;
    public bool $enableSendResult = true;

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function checkMobile($mobile, $iddCode = null): bool
    {
        return $this->checkMobileResult;
    }

    protected function enableSend(): bool
    {
        return $this->enableSendResult;
    }

    protected function getPinCode($mobile, $iddCode): string
    {
        return '1234';
    }

    protected function send($mobile, $iddCode, $pinCode): void
    {
        $this->sendCalled = true;
    }
}

class SendPinCodeSmsAction extends Controller
{
    use SendPinCodeSms;

    public Request $request;
    public bool $sendCalled = false;

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function checkMobile($mobile, $iddCode = null): bool
    {
        return true;
    }

    protected function getPinCode($mobile, $iddCode): string
    {
        return '1234';
    }

    protected function send($mobile, $iddCode, $pinCode): void
    {
        $this->sendCalled = true;
    }
}

class ValidatePinCodeSmsAction extends Controller
{
    use ValidatePinCodeSms;

    public Request $request;
    public bool $validateResult = true;

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function checkMobile($mobile, $iddCode = null): bool
    {
        return true;
    }

    protected function runValidatePinCode($mobile, $iddCode, $pincode, $deleteAfterSuccess): bool
    {
        return $this->validateResult;
    }
}

class MobileTraitsTest extends TestCase
{
    public function testGetMobileReadsRequest()
    {
        $action = new MobileTraitsGetMobileAction();
        $action->request = Request::create('/', 'GET', [
            'mobile' => '13800138000',
            'idd_code' => 86,
        ]);

        $this->assertSame('13800138000', $this->callMethod($action, 'getMobile'));
        $this->assertEquals(86, $this->callMethod($action, 'getIDDCode'));
    }

    public function testGetMobileResponsesThrow()
    {
        $action = new MobileTraitsGetMobileAction();
        $action->request = Request::create('/', 'GET');

        $this->expectException(MobileEmptyException::class);
        $this->callMethod($action, 'emptyMobileResponse');
    }

    public function testInvalidMobileResponseThrows()
    {
        $action = new MobileTraitsGetMobileAction();
        $action->request = Request::create('/', 'GET');

        $this->expectException(MobileInvalidException::class);
        $this->callMethod($action, 'invalidMobileResponse');
    }

    public function testCheckMobileValidatesWithIddCode()
    {
        $action = new MobileTraitsGetMobileAction();
        $action->request = Request::create('/', 'GET');

        $this->assertTrue($this->callMethod($action, 'checkMobile', ['13800138000', 86]));
        $this->assertFalse($this->callMethod($action, 'checkMobile', ['abc', 86]));
        $this->assertTrue($this->callMethod($action, 'checkMobile', ['abc', 1]));
    }

    public function testPinCodeSmsActionSendsWhenValid()
    {
        $action = new PinCodeSmsAction();
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
        ]);

        $response = $this->callMethod($action, 'action');
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($action->sendCalled);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Success', $data['Code']);
    }

    public function testPinCodeSmsActionSkipsSendWhenDisabled()
    {
        $action = new PinCodeSmsAction();
        $action->enableSendResult = false;
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
        ]);

        $this->callMethod($action, 'action');
        $this->assertFalse($action->sendCalled);
    }

    public function testPinCodeSmsActionThrowsForEmptyMobile()
    {
        $action = new PinCodeSmsAction();
        $action->request = Request::create('/', 'POST');

        $this->expectException(MobileEmptyException::class);
        $this->callMethod($action, 'action');
    }

    public function testPinCodeSmsActionThrowsForInvalidMobile()
    {
        $action = new PinCodeSmsAction();
        $action->checkMobileResult = false;
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
        ]);

        $this->expectException(MobileInvalidException::class);
        $this->callMethod($action, 'action');
    }

    public function testSendPinCodeSmsReturnsJsonResponse()
    {
        $action = new SendPinCodeSmsAction();
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
        ]);

        $response = $this->callMethod($action, 'action');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $data['code']);
        $this->assertSame('success', $data['message']);
    }

    public function testValidatePinCodeSmsPassesWhenValid()
    {
        $action = new ValidatePinCodeSmsAction();
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
            'pincode' => '1234',
        ]);

        $this->callMethod($action, 'validatePinCode', [true]);
        $this->assertTrue(true);
    }

    public function testValidatePinCodeSmsThrowsWhenInvalid()
    {
        $action = new ValidatePinCodeSmsAction();
        $action->validateResult = false;
        $action->request = Request::create('/', 'POST', [
            'mobile' => '13800138000',
            'idd_code' => 86,
            'pincode' => '1234',
        ]);

        $this->expectException(ValidatePinCodeException::class);
        $this->callMethod($action, 'validatePinCode', [true]);
    }
}
