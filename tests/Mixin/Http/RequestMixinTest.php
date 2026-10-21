<?php

namespace HughCube\Laravel\Knight\Tests\Mixin\Http;

use HughCube\Laravel\Knight\Mixin\Http\RequestMixin;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\HeaderBag;

class RequestMixinStub extends Request
{
    public function getClientHeaderPrefix(): string
    {
        return 'X-Client-';
    }
}

class RequestMixinTest extends TestCase
{
    public function testClientHeadersAndVersionHelpers()
    {
        RequestMixinStub::mixin(new RequestMixin());

        $request = RequestMixinStub::create('/api/v1/users', 'GET');
        $request->headers->set('X-Client-Version', '1.2.3');
        $request->headers->set('X-Client-Nonce', 'nonce');
        $request->headers->set('X-Client-Signature', 'sig');
        $request->headers->set('X-Client-Date', '2023-01-01');
        $request->headers->set('Date', '2023-01-02');
        $request->headers->set('X-Other', 'nope');

        $this->assertSame('1.2.3', $request->getClientVersion());
        $this->assertSame('nonce', $request->getClientNonce());
        $this->assertSame('sig', $request->getClientSignature());
        $this->assertSame('2023-01-02', $request->getDate());
        $this->assertSame('2023-01-01', $request->getClientDate());

        $headers = $request->getClientHeaders();
        $this->assertInstanceOf(HeaderBag::class, $headers);
        $this->assertTrue($headers->has('X-Client-Version'));
        $this->assertFalse($headers->has('X-Other'));

        $this->assertTrue($request->isEqClientVersion('1.2.3'));
        $this->assertTrue($request->isLtClientVersion('1.0.0'));
        $this->assertTrue($request->isGtClientVersion('2.0.0'));

        $this->assertSame('users', $request->getLastDirectory());
    }

    public function testUserAgentHelpers()
    {
        RequestMixinStub::mixin(new RequestMixin());

        $request = RequestMixinStub::create('/ua', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'MicroMessenger miniProgram',
        ]);

        if (class_exists(Agent::class)) {
            $this->assertInstanceOf(Agent::class, $request->getUserAgentDetect());
            $this->assertSame($request->getUserAgentDetect(), $request->getUserAgentDetect());
        } else {
            $hasException = false;
            try {
                $request->getUserAgentDetect();
            } catch (\BadMethodCallException $e) {
                $hasException = true;
            }
            $this->assertTrue($hasException);
        }
        $this->assertTrue($request->isWeChat());
        $this->assertTrue($request->isWeChatMiniProgram());
        $this->assertFalse($request->isPostmen());

        $postmanRequest = RequestMixinStub::create('/ua', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'PostmanRuntime/7.0',
        ]);

        $this->assertTrue($postmanRequest->isPostmen());
    }
}
