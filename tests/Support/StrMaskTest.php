<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use HughCube\Laravel\Knight\Support\Str;
use HughCube\Laravel\Knight\Tests\TestCase;

class StrMaskTest extends TestCase
{
    public function testMaskEmail()
    {
        $this->assertSame('u**r@example.com', Str::maskEmail('user@example.com'));
        $this->assertSame('a*@example.com', Str::maskEmail('ab@example.com'));
        $this->assertSame('*@example.com', Str::maskEmail('a@example.com'));
        $this->assertSame('t*************g@example.com', Str::maskEmail('testing1234567g@example.com'));
        $this->assertSame('', Str::maskEmail(''));
        $this->assertSame('', Str::maskEmail(null));
        $this->assertSame('noemail', Str::maskEmail('noemail'));
    }

    public function testMaskBankCard()
    {
        $this->assertSame('6222********1234', Str::maskBankCard('6222123456781234'));
        $this->assertSame('6222*****4321', Str::maskBankCard('6222912344321'));
        $this->assertSame('12345678', Str::maskBankCard('12345678'));
        $this->assertSame('', Str::maskBankCard(''));
        $this->assertSame('', Str::maskBankCard(null));
    }

    public function testMaskName()
    {
        $this->assertSame('张*', Str::maskName('张三'));
        $this->assertSame('张*丰', Str::maskName('张三丰'));
        $this->assertSame('欧**强', Str::maskName('欧阳自强'));
        $this->assertSame('*', Str::maskName('张'));
        $this->assertSame('', Str::maskName(''));
        $this->assertSame('', Str::maskName(null));
    }

    public function testMaskAddress()
    {
        $this->assertSame('北京市海淀区*****', Str::maskAddress('北京市海淀区中关村大街'));
        $this->assertSame('上海市浦东新***********', Str::maskAddress('上海市浦东新区张江高科技园区博云路'));
        $this->assertSame('短地址', Str::maskAddress('短地址'));
        $this->assertSame('', Str::maskAddress(''));
        $this->assertSame('', Str::maskAddress(null));
        $this->assertSame('北京**', Str::maskAddress('北京市海', 2));
    }

    public function testMaskPlateNumber()
    {
        $this->assertSame('京A****8', Str::maskPlateNumber('京A123B8'));
        $this->assertSame('粤B****5', Str::maskPlateNumber('粤B85XS5'));
        $this->assertSame('京A', Str::maskPlateNumber('京A'));
        $this->assertSame('', Str::maskPlateNumber(''));
        $this->assertSame('', Str::maskPlateNumber(null));
        $this->assertSame('京A*5', Str::maskPlateNumber('京A15'));
    }

    /**
     * 多字节安全: 传入中文人名不应产生非法 UTF-8 导致 json_encode 失败.
     */
    public function testMaskMethodsMultiByteSafety()
    {
        $methods = [
            'maskMobile' => ['王从辉'],
            'maskChinaIdCode' => ['王从辉'],
            'maskEmail' => ['王从辉@example.com'],
            'maskBankCard' => ['王从辉'],
            'maskName' => ['王从辉'],
            'maskAddress' => ['王从辉'],
            'maskPlateNumber' => ['王从辉'],
        ];

        foreach ($methods as $method => $args) {
            $result = Str::$method(...$args);
            $this->assertIsString($result, "$method should return a string");
            $this->assertTrue(
                mb_check_encoding($result, 'UTF-8'),
                "$method must return valid UTF-8, got: " . bin2hex($result)
            );

            // json_encode 不应失败
            $json = json_encode([$result]);
            $this->assertNotFalse(
                $json,
                "$method result must be json_encode-able, error: " . json_last_error_msg()
            );
        }
    }

    /**
     * 多字节安全: maskEmail 的 @ 前后都有中文.
     */
    public function testMaskEmailWithMultiByte()
    {
        $result = Str::maskEmail('欧阳自强@测试.com');
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertStringContainsString('@', $result);
        $this->assertStringContainsString('*', $result);
    }

    /**
     * 正常输入(maskMobile/maskChinaIdCode)行为不变.
     */
    public function testMaskMobileAndIdCodeNormalInput()
    {
        $this->assertSame('138****8000', Str::maskMobile('13800138000'));
        $this->assertSame('123456********5678', Str::maskChinaIdCode('123456789012345678'));
    }

    /**
     * 空/null 输入返回空字符串.
     */
    public function testMaskMethodsEmptyInput()
    {
        foreach (['maskMobile', 'maskChinaIdCode', 'maskEmail', 'maskBankCard'] as $method) {
            $this->assertSame('', Str::$method(''), "$method('') should return ''");
            $this->assertSame('', Str::$method(null), "$method(null) should return ''");
        }
    }
}
