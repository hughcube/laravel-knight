<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/1
 * Time: 14:36.
 */

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\Laravel\Knight\Traits\Validation;
use Illuminate\Http\Request;

class ValidationTest extends TestCase
{
    public function testMake()
    {
        $this->assertNoException(function () {
            $instance = new class()
            {
                use Validation;
            };

            $this->callMethod($instance, 'validate', [[]]);
        });
    }

    public function testValidateWithRulesAndRequest()
    {
        $instance = new class()
        {
            use Validation;

            protected function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                    'age' => ['required', 'integer'],
                ];
            }
        };

        $request = Request::create('/validate', 'POST', [
            'name' => 'Bob',
            'age' => 20,
        ]);

        $data = $this->callMethod($instance, 'validate', [$request]);

        $this->assertSame('Bob', $data['name']);
        $this->assertSame(20, (int) $data['age']);
    }

    public function testValidateUsesValidWhenValidateReturnsNull()
    {
        $factory = new class()
        {
            public function make(array $data, array $rules, array $messages = [], array $attributes = [])
            {
                return new class()
                {
                    public function validate()
                    {
                        return null;
                    }

                    public function valid()
                    {
                        return ['name' => 'Ada'];
                    }
                };
            }
        };

        $container = new class($factory)
        {
            private $factory;

            public function __construct($factory)
            {
                $this->factory = $factory;
            }

            public function make($abstract)
            {
                return $this->factory;
            }
        };

        $instance = new class($container)
        {
            use Validation;

            private $container;

            public function __construct($container)
            {
                $this->container = $container;
            }

            protected function rules(): array
            {
                return [
                    'name' => ['string'],
                ];
            }

            protected function getContainer()
            {
                return $this->container;
            }
        };

        $data = $this->callMethod($instance, 'validate', [['name' => 'Ada']]);

        $this->assertSame(['name' => 'Ada'], $data);
    }
}
