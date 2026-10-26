<?php

namespace HughCube\Laravel\Knight\Tests\Traits;

use HughCube\Laravel\Knight\Contracts\Support\GetKnightSortValue;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\Laravel\Knight\Traits\GetKnightSortValueTrait;
use Illuminate\Support\Collection;

class GetKnightSortValueTraitTest extends TestCase
{
    public function testImplementsInterface()
    {
        $obj = new TestSortValueObject(10, 1);
        $this->assertInstanceOf(GetKnightSortValue::class, $obj);
    }

    public function testSortValueLength()
    {
        $obj = new TestSortValueObject(10, 1);
        $this->assertSame(80, strlen($obj->getKSortValue()));
    }

    public function testSortValueFormat()
    {
        $obj = new TestSortValueObject(100, 5);
        $value = $obj->getKSortValue();

        $sortPart = substr($value, 0, 40);
        $idPart = substr($value, 40, 40);

        $this->assertSame(str_pad('100', 40, '0', STR_PAD_LEFT), $sortPart);
        $this->assertSame(str_pad('5', 40, '0', STR_PAD_LEFT), $idPart);
    }

    public function testSortValueWithZeros()
    {
        $obj = new TestSortValueObject(0, 0);
        $value = $obj->getKSortValue();

        $this->assertSame(str_repeat('0', 80), $value);
    }

    public function testSortValueWithLargeNumbers()
    {
        $obj = new TestSortValueObject(PHP_INT_MAX, PHP_INT_MAX);
        $value = $obj->getKSortValue();

        $this->assertSame(80, strlen($value));
        $this->assertSame(
            str_pad(strval(PHP_INT_MAX), 40, '0', STR_PAD_LEFT)
            .str_pad(strval(PHP_INT_MAX), 40, '0', STR_PAD_LEFT),
            $value
        );
    }

    public function testHigherSortProducesLargerValue()
    {
        $a = new TestSortValueObject(20, 1);
        $b = new TestSortValueObject(10, 1);

        $this->assertGreaterThan(0, strcmp($a->getKSortValue(), $b->getKSortValue()));
    }

    public function testSameSortHigherIdProducesLargerValue()
    {
        $a = new TestSortValueObject(10, 5);
        $b = new TestSortValueObject(10, 3);

        $this->assertGreaterThan(0, strcmp($a->getKSortValue(), $b->getKSortValue()));
    }

    public function testSameSortSameIdProducesEqualValue()
    {
        $a = new TestSortValueObject(10, 5);
        $b = new TestSortValueObject(10, 5);

        $this->assertSame(0, strcmp($a->getKSortValue(), $b->getKSortValue()));
    }

    public function testSortKnightModelWithGetKnightSortValue()
    {
        $collection = Collection::make([
            new TestSortValueObject(10, 1),
            new TestSortValueObject(30, 2),
            new TestSortValueObject(20, 3),
        ]);

        $sorted = $collection->sortKnightModel();
        $ids = $sorted->map(function ($item) {
            return $item->id;
        })->values()->toArray();

        $this->assertSame([2, 3, 1], $ids);
    }

    public function testSortKnightModelWithSameSortDescById()
    {
        $collection = Collection::make([
            new TestSortValueObject(10, 5),
            new TestSortValueObject(10, 3),
            new TestSortValueObject(10, 8),
        ]);

        $sorted = $collection->sortKnightModel();
        $ids = $sorted->map(function ($item) {
            return $item->id;
        })->values()->toArray();

        $this->assertSame([8, 5, 3], $ids);
    }

    public function testSortKnightModelEmptyCollection()
    {
        $collection = Collection::make([]);
        $sorted = $collection->sortKnightModel();

        $this->assertSame([], $sorted->toArray());
    }
}

/**
 * 测试用的排序值对象
 */
class TestSortValueObject implements GetKnightSortValue
{
    use GetKnightSortValueTrait;

    /**
     * @var int
     */
    public $sort;

    /**
     * @var int
     */
    public $id;

    /**
     * @param  int  $sort
     * @param  int  $id
     */
    public function __construct($sort, $id)
    {
        $this->sort = $sort;
        $this->id = $id;
    }
}
