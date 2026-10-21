<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/30
 * Time: 16:50.
 */

namespace HughCube\Laravel\Knight\Tests\Http;

use HughCube\Laravel\Knight\Http\Request;
use HughCube\Laravel\Knight\Mixin\Http\RequestMixin;
use HughCube\Laravel\Knight\Tests\TestCase;
use Jenssegers\Agent\Agent;

/**
 * @see RequestMixin
 */
class RequestTest extends TestCase
{
    /**
     * @see Request::getUserAgentDetect()
     */
    public function testGetUserAgentDetect()
    {
        $request = Request::capture();

        if (class_exists(Agent::class)) {
            $this->assertInstanceOf(Agent::class, $agent = $request->getUserAgentDetect());
            $this->assertSame($request->getUserAgentDetect(), $agent);
        } else {
            $this->expectException(\BadMethodCallException::class);
            $request->getUserAgentDetect();
        }
    }

    /**
     * @see Request::isWeChat()
     */
    public function testIsWeChat()
    {
        $this->assertFalse(Request::capture()->isWeChat());
    }

    /**
     * @see Request::isWeChatMiniProgram()
     */
    public function testIsWeChatMiniProgram()
    {
        $this->assertFalse(Request::capture()->isWeChatMiniProgram());
    }

    /**
     * @see Request::isEqClientVersion()
     */
    public function testIsEqClientVersion()
    {
        $request = Request::capture();
        $request->headers->set('Version', '1.0.0');

        $this->assertTrue($request->isEqClientVersion('1', 1));
        $this->assertTrue($request->isEqClientVersion('1.0', 2));
        $this->assertTrue($request->isEqClientVersion('1.0.0', 3));
        $this->assertTrue($request->isEqClientVersion('1.0.0'));
        $this->assertFalse($request->isEqClientVersion('2.0.0'));
    }

    /**
     * @see Request::isLtClientVersion()
     */
    public function testIsLtClientApiVersion()
    {
        $request = Request::capture();
        $request->headers->set('Version', '2.2.2');

        $this->assertTrue($request->isLtClientVersion('2', true));
        $this->assertTrue($request->isLtClientVersion('2'));

        $this->assertTrue($request->isLtClientVersion('2.2', true));
        $this->assertTrue($request->isLtClientVersion('2.1'));

        $this->assertTrue($request->isLtClientVersion('2.2.2', true));
        $this->assertTrue($request->isLtClientVersion('2.2.1'));

        $this->assertTrue($request->isLtClientVersion('2.2.2', true));

        $this->assertFalse($request->isLtClientVersion('3.0.0'));
    }

    /**
     * @see Request::isGtClientVersion()
     */
    public function testIsLtClientVersion()
    {
        $request = Request::capture();

        $request->headers->set('Version', '2.2.2');

        $this->assertTrue($request->isGtClientVersion('3', true));
        $this->assertTrue($request->isGtClientVersion('3'));

        $this->assertTrue($request->isGtClientVersion('3.2', true));
        $this->assertTrue($request->isGtClientVersion('3.1'));

        $this->assertTrue($request->isGtClientVersion('3.2.2', true));
        $this->assertTrue($request->isGtClientVersion('3.2.1'));

        $this->assertTrue($request->isGtClientVersion('3.2.2', true));

        $this->assertFalse($request->isGtClientVersion('2.0.0'));
    }

    public function testClientHeadersAndDates()
    {
        $request = new class() extends Request {
            public function getClientHeaderPrefix(): string
            {
                return 'X-Client-';
            }
        };

        $request->headers->set('X-Client-Version', '1.2.3');
        $request->headers->set('X-Client-Nonce', 'nonce');
        $request->headers->set('X-Client-Signature', 'sig');
        $request->headers->set('X-Client-Date', 'Mon, 02 Jan 2006 15:04:05 GMT');
        $request->headers->set('Date', 'Tue, 03 Jan 2006 15:04:05 GMT');
        $request->headers->set('Other', 'value');

        $this->assertSame('1.2.3', $request->getClientVersion());
        $this->assertSame('nonce', $request->getClientNonce());
        $this->assertSame('sig', $request->getClientSignature());
        $this->assertSame('Mon, 02 Jan 2006 15:04:05 GMT', $request->getClientDate());
        $this->assertSame('Tue, 03 Jan 2006 15:04:05 GMT', $request->getDate());

        $headers = $request->getClientHeaders()->all();
        $this->assertArrayHasKey('x-client-version', $headers);
        $this->assertArrayHasKey('x-client-nonce', $headers);
        $this->assertArrayHasKey('x-client-signature', $headers);
        $this->assertArrayHasKey('x-client-date', $headers);
        $this->assertArrayNotHasKey('other', $headers);
    }

    public function testUserAgentFlagsAndLastDirectory()
    {
        $wechat = Request::create('/foo/bar', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'MicroMessenger miniProgram',
            'HTTP_HOST'       => 'example.test:8080',
        ]);

        $this->assertTrue($wechat->isWeChat());
        $this->assertTrue($wechat->isWeChatMiniProgram());
        $this->assertSame('bar', $wechat->getLastDirectory());
        $this->assertSame(8080, $wechat->getPort());

        $postman = Request::create('/api', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'PostmanRuntime/7.32.3',
        ]);

        $this->assertTrue($postman->isPostmen());
    }
}
