<?php

namespace HughCube\Laravel\Knight\Tests\Database\Eloquent;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CollectionTest extends TestCase
{
    public function testFilterAvailable()
    {
        $available = new class()
        {
            public function isAvailable(): bool
            {
                return true;
            }
        };

        $unavailable = new class()
        {
            public function isAvailable(): bool
            {
                return false;
            }
        };

        $collection = new EloquentCollection([$available, $unavailable]);

        $filtered = $collection->filterAvailable()->values();

        $this->assertCount(1, $filtered);
        $this->assertSame($available, $filtered->first());
    }
}
