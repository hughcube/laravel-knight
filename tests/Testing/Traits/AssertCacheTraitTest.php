<?php

namespace HughCube\Laravel\Knight\Tests\Testing\Traits;

use HughCube\Laravel\Knight\Testing\Traits\AssertCacheTrait;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class AssertCacheTraitTest extends TestCase
{
    use AssertCacheTrait;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache', [
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => 'array',
                    'serialize' => false,
                ],
            ],
        ]);
    }

    public function testAssertCacheHas()
    {
        Cache::put('test-key', 'value', 60);

        $this->assertCacheHas('test-key');
    }

    public function testAssertCacheMissing()
    {
        $this->assertCacheMissing('nonexistent-key');
    }

    public function testAssertCacheEquals()
    {
        Cache::put('test-key', 'expected-value', 60);

        $this->assertCacheEquals('expected-value', 'test-key');
    }

    public function testAssertCacheEqualsWithArray()
    {
        Cache::put('array-key', ['foo' => 'bar'], 60);

        $this->assertCacheEquals(['foo' => 'bar'], 'array-key');
    }
}
