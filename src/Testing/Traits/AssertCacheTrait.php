<?php

namespace HughCube\Laravel\Knight\Testing\Traits;

use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

trait AssertCacheTrait
{
    /**
     * 断言缓存中存在指定 key.
     *
     * @param  string  $key
     * @param  string|null  $store
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertCacheHas(string $key, ?string $store = null): void
    {
        $cache = null !== $store ? Cache::store($store) : Cache::store();
        $this->assertTrue($cache->has($key), "Expected cache key [{$key}] to exist, but it does not.");
    }

    /**
     * 断言缓存中不存在指定 key.
     *
     * @param  string  $key
     * @param  string|null  $store
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertCacheMissing(string $key, ?string $store = null): void
    {
        $cache = null !== $store ? Cache::store($store) : Cache::store();
        $this->assertFalse($cache->has($key), "Expected cache key [{$key}] to not exist, but it does.");
    }

    /**
     * 断言缓存中指定 key 的值等于期望值
     *
     * @param  mixed  $expected
     * @param  string  $key
     * @param  string|null  $store
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function assertCacheEquals($expected, string $key, ?string $store = null): void
    {
        $cache = null !== $store ? Cache::store($store) : Cache::store();
        $this->assertEquals(
            $expected,
            $cache->get($key),
            "Expected cache key [{$key}] to equal the expected value."
        );
    }
}
