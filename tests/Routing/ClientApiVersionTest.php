<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/5/18
 * Time: 11:47.
 */

namespace HughCube\Laravel\Knight\Tests\Routing;

use HughCube\Laravel\Knight\Routing\ClientApiVersion;
use HughCube\Laravel\Knight\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

class ClientApiVersionTest extends TestCase
{
    /**
     * @dataProvider instanceDataProvider
     *
     * @throws ReflectionException
     */
    #[DataProvider('instanceDataProvider')]
    public function testClientApiVersionFormat($instance)
    {
        $this->assertSame('1', $this->callMethod($instance, 'clientApiVersionFormat', ['1.2.2', 1]));
        $this->assertSame('1.2', $this->callMethod($instance, 'clientApiVersionFormat', ['1.2.2', 2]));
        $this->assertSame('1.2.2', $this->callMethod($instance, 'clientApiVersionFormat', ['1.2.2', 3]));
        $this->assertSame('1.2.2.0', $this->callMethod($instance, 'clientApiVersionFormat', ['1.2.2', 4]));
        $this->assertSame('1.2.2.0.0', $this->callMethod($instance, 'clientApiVersionFormat', ['1.2.2', 5]));
    }

    /**
     * @dataProvider instanceDataProvider
     *
     * @throws ReflectionException
     */
    #[DataProvider('instanceDataProvider')]
    public function testClientApiVersionCompare($instance)
    {
        $this->assertTrue($this->callMethod($instance, 'clientApiVersionCompare', ['=', '1.1.1']));
        $this->assertTrue($this->callMethod($instance, 'clientApiVersionCompare', ['<', '1.2.2']));
        $this->assertTrue($this->callMethod($instance, 'clientApiVersionCompare', ['>', '0.0.1']));
    }

    /**
     * @dataProvider instanceDataProvider
     *
     * @throws ReflectionException
     */
    #[DataProvider('instanceDataProvider')]
    public function testIsEqClientApiVersion($instance)
    {
        $this->assertTrue($this->callMethod($instance, 'isEqClientApiVersion', ['1.1.1']));
        $this->assertFalse($this->callMethod($instance, 'isEqClientApiVersion', ['1.2.2']));
    }

    /**
     * @dataProvider instanceDataProvider
     *
     * @throws ReflectionException
     */
    #[DataProvider('instanceDataProvider')]
    public function testIsLtClientApiVersion($instance)
    {
        $this->assertFalse($this->callMethod($instance, 'isLtClientApiVersion', ['2.1.1']));
        $this->assertTrue($this->callMethod($instance, 'isLtClientApiVersion', ['0.0.1']));
    }

    /**
     * @dataProvider instanceDataProvider
     *
     * @throws ReflectionException
     */
    #[DataProvider('instanceDataProvider')]
    public function testIsGtClientApiVersion($instance)
    {
        $this->assertTrue($this->callMethod($instance, 'isGtClientApiVersion', ['2.1.1']));
        $this->assertFalse($this->callMethod($instance, 'isGtClientApiVersion', ['0.0.1']));
    }

    public static function instanceDataProvider(): array
    {
        return [
            [
                new class()
                {
                    use ClientApiVersion;

                    protected function getClientApiVersion(): string
                    {
                        return '1.1.1';
                    }
                },
            ],
        ];
    }
}
