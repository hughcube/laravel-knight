<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use ArrayIterator;
use HughCube\Laravel\Knight\Support\ParameterBag;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ParameterBagBehaviorTest extends TestCase
{
    public function testAccessorsAndMutators()
    {
        $bag = new ParameterBag(['a' => 1, 'b' => null, 'c' => '', 0 => 'zero']);

        $this->assertSame(['a' => 1, 'b' => null, 'c' => '', 0 => 'zero'], $bag->all());
        $this->assertSame(['a', 'b', 'c', 0], $bag->keys());
        $this->assertTrue($bag->has('a'));
        $this->assertFalse($bag->has('missing'));
        $this->assertSame('default', $bag->get('missing', 'default'));
        $this->assertTrue($bag->isNull('b'));
        $this->assertTrue($bag->isEmpty('c'));

        $bag->set('d', 4);
        $this->assertSame(4, $bag->get('d'));

        $bag->add('d', 5);
        $this->assertSame(5, $bag->get('d'));
        $bag->add('missing', 99);
        $this->assertFalse($bag->has('missing'));

        $bag->remove('a');
        $this->assertFalse($bag->has('a'));

        $bag->replace(['x' => 10]);
        $this->assertSame(['x' => 10], $bag->all());
        $this->assertSame(1, $bag->count());
        $this->assertInstanceOf(ArrayIterator::class, $bag->getIterator());
    }

    public function testTypeConversions()
    {
        $bag = new ParameterBag([
            'bool_yes' => 'yes',
            'bool_no' => '0',
            'bool_unknown' => 'maybe',
            'int' => '12',
            'int_bad' => '12.3',
            'float' => '12.3',
            'alpha' => 'abc',
            'alnum' => 'abc123',
            'digits' => '007',
            'string_int' => 42,
            'string_bool' => true,
            'string_obj' => new class()
            {
                public function __toString()
                {
                    return 'obj';
                }
            },
            'array' => ['a'],
        ]);

        $this->assertTrue($bag->getBoolean('bool_yes'));
        $this->assertFalse($bag->getBoolean('bool_no', true));
        $this->assertTrue($bag->getBoolean('bool_unknown', true));

        $this->assertSame(12, $bag->getInt('int'));
        $this->assertSame(5, $bag->getInt('int_bad', 5));
        $this->assertSame(12.3, $bag->getFloat('float'));

        $this->assertSame('abc', $bag->getAlpha('alpha'));
        $this->assertSame('default', $bag->getAlpha('alnum', 'default'));
        $this->assertSame('abc123', $bag->getAlnum('alnum'));
        $this->assertSame('007', $bag->getDigits('digits'));

        $this->assertSame('42', $bag->getString('string_int'));
        $this->assertSame('1', $bag->getString('string_bool'));
        $this->assertSame('obj', $bag->getString('string_obj'));
        $this->assertSame('fallback', $bag->getString('missing', 'fallback'));

        $this->assertSame(['a'], $bag->getArray('array'));
        $this->assertSame([], $bag->getArray('missing'));
    }

    public function testWhenHelpers()
    {
        $bag = new ParameterBag([
            'value' => 10,
            'null' => null,
            'empty' => '',
            'zero' => '0',
        ]);

        $result = $bag->when(true, 'value', function ($value, $key, $bag) {
            $this->assertSame(10, $value);
            $this->assertSame('value', $key);
            $this->assertInstanceOf(ParameterBag::class, $bag);

            return 'called';
        }, 'default');
        $this->assertSame('called', $result);

        $this->assertSame('default', $bag->when(false, 'value', function () {
            return 'called';
        }, 'default'));

        $this->assertSame('value', $bag->when(false, 'value', function () {
            return 'called';
        }, function ($value, $key) {
            return $key;
        }));

        $this->assertSame('has-null', $bag->whenHas('null', function () {
            return 'has-null';
        }, 'default'));

        $this->assertSame('default', $bag->whenNotNull('null', function () {
            return 'should-not';
        }, 'default'));

        $this->assertSame('default', $bag->whenNotEmpty('empty', function () {
            return 'should-not';
        }, 'default'));

        $this->assertSame('default', $bag->whenNotEmpty('zero', function () {
            return 'should-not';
        }, 'default'));

        $this->assertSame(10, $bag->whenNotEmpty('value', function ($value) {
            return $value;
        }, 'default'));
    }

    public function testQueryWhenHelpers()
    {
        $bag = new ParameterBag([
            'value' => 'yes',
            'null' => null,
            'empty' => '',
        ]);

        $query = DB::table('users');

        $called = 0;
        $bag->queryWhen(true, 'value', $query, function ($query, $value, $key, $bag) use (&$called) {
            $called++;
            $this->assertSame('yes', $value);
            $this->assertSame('value', $key);
            $this->assertInstanceOf(ParameterBag::class, $bag);
            $query->where('id', '=', 1);
        });

        $bag->queryWhen(false, 'value', $query, function () use (&$called) {
            $called++;
        });

        $bag->queryWhenHas('null', $query, function () use (&$called) {
            $called++;
        });

        $bag->queryWhenNotNull('null', $query, function () use (&$called) {
            $called++;
        });

        $bag->queryWhenNotEmpty('empty', $query, function () use (&$called) {
            $called++;
        });

        $bag->queryWhenNotEmpty('value', $query, function () use (&$called) {
            $called++;
        });

        $this->assertSame(3, $called);
    }
}
