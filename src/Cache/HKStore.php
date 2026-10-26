<?php

namespace HughCube\Laravel\Knight\Cache;

use DateInterval;
use Illuminate\Support\InteractsWithTime;

class HKStore
{
    use InteractsWithTime;

    /**
     * The array of stored values.
     *
     * @var array
     */
    protected array $storage = [];

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->has($key) ? $this->storage[$key]['value'] : $default;
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @param  DateInterval|int|null  $ttl
     * @return bool
     */
    public function set(string $key, $value, $ttl = null): bool
    {
        $this->storage[$key] = [
            'value' => $value,
            'expiresAt' => null === $ttl ? null : $this->availableAt($ttl),
        ];

        return true;
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset($this->storage[$key]);

        return true;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    /**
     * @param  iterable  $keys
     * @param  mixed  $default
     * @return iterable
     */
    public function getMultiple(iterable $keys, $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param  iterable  $values
     * @param  DateInterval|int|null  $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param  iterable  $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (! isset($this->storage[$key])) {
            return false;
        }

        $expiresAt = $this->storage[$key]['expiresAt'] ?? 0;
        if ($expiresAt === 0 || $this->currentTime() <= $expiresAt) {
            return true;
        }

        $this->delete($key);

        return false;
    }

    /**
     * @param  string  $key
     * @param  callable  $callable
     * @param  DateInterval|int|null  $ttl
     * @return mixed
     */
    public function getOrSet(string $key, callable $callable, $ttl = null)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $this->set($key, $value = $callable(), $ttl);

        return $value;
    }

    /**
     * @return void
     */
    public function gc()
    {
        foreach ($this->storage as $key => $_) {
            $this->has($key);
        }
    }
}
