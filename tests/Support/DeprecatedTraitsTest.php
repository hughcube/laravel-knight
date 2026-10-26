<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use HughCube\GuzzleHttp\Client;
use HughCube\Laravel\Knight\Support\GetOrSet;
use HughCube\Laravel\Knight\Support\HttpClient;
use HughCube\Laravel\Knight\Support\MultipleHandler;
use HughCube\Laravel\Knight\Support\ParameterBag;
use HughCube\Laravel\Knight\Support\ParameterBagTrait;
use HughCube\Laravel\Knight\Support\Validation;
use HughCube\Laravel\Knight\Tests\TestCase;
use ReflectionMethod;

class DeprecatedTraitsTest extends TestCase
{
    public function testSupportGetOrSetTraitCachesValues()
    {
        $instance = new class()
        {
            use GetOrSet;
        };

        $calls = 0;
        $value = self::callMethod($instance, 'getOrSet', [
            'key',
            function () use (&$calls) {
                $calls++;

                return 'value';
            },
        ]);

        $this->assertSame('value', $value);
        $this->assertSame(1, $calls);

        $value = self::callMethod($instance, 'getOrSet', [
            'key',
            function () use (&$calls) {
                $calls++;

                return 'other';
            },
        ]);

        $this->assertSame('value', $value);
        $this->assertSame(1, $calls);

        $instance->flushHughCubeKnightClassSelfCacheStorage();

        $value = self::callMethod($instance, 'getOrSet', [
            'key',
            function () use (&$calls) {
                $calls++;

                return 'new';
            },
        ]);

        $this->assertSame('new', $value);
        $this->assertSame(2, $calls);
    }

    public function testSupportParameterBagTraitLoadsParameters()
    {
        $instance = new class()
        {
            use ParameterBagTrait;

            protected function loadParameters()
            {
                $this->parameterBag = new ParameterBag(['foo' => 'bar']);
            }
        };

        $bag = self::callMethod($instance, 'p');

        $this->assertInstanceOf(ParameterBag::class, $bag);
        $this->assertSame('bar', self::callMethod($instance, 'p', ['foo']));
        $this->assertSame('default', self::callMethod($instance, 'p', ['missing', 'default']));
    }

    public function testSupportValidationTraitUsesRules()
    {
        $instance = new class()
        {
            use Validation;

            protected function rules(): array
            {
                return ['foo' => 'required'];
            }
        };

        $validated = self::callMethod($instance, 'validate', [['foo' => 'bar', 'extra' => 'x']]);

        $this->assertSame(['foo' => 'bar'], $validated);
    }

    public function testSupportMultipleHandlerTraitExecutesHandlers()
    {
        $instance = new class()
        {
            use MultipleHandler;

            public array $done = [];

            protected function bHandler()
            {
                $this->done[] = __FUNCTION__;
            }

            protected function aHandler100()
            {
                $this->done[] = __FUNCTION__;
            }

            protected function cHandler9()
            {
                $this->done[] = __FUNCTION__;
            }
        };

        $methods = array_map(function (ReflectionMethod $method) {
            return $method->name;
        }, self::callMethod($instance, 'getMultipleHandlers'));

        $this->assertSame(['bHandler', 'cHandler9', 'aHandler100'], array_values($methods));

        $this->assertSame([], $instance->done);
        self::callMethod($instance, 'triggerMultipleHandlers');
        $this->assertSame(['bHandler', 'cHandler9', 'aHandler100'], $instance->done);
    }

    public function testSupportHttpClientTraitUsesCustomCreator()
    {
        $instance = new class()
        {
            use HttpClient;

            protected function createHttpClient(): Client
            {
                return new Client(['timeout' => 1]);
            }
        };

        $client = self::callMethod($instance, 'getHttpClient');
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, self::callMethod($instance, 'getHttpClient'));
    }

    public function testSupportHttpClientTraitFallsBackToDefault()
    {
        $instance = new class()
        {
            use HttpClient;
        };

        $client = self::callMethod($instance, 'getHttpClient');
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, self::callMethod($instance, 'getHttpClient'));
    }
}
