<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/1/31
 * Time: 11:46.
 */

namespace HughCube\Laravel\Knight\Cache\Listeners;

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Log;

class LogMissedKey
{
    /**
     * @param  CacheMissed  $event
     * @return void
     */
    public function handle(CacheEvent $event)
    {
        Log::debug(sprintf(
            'cache missed: store: %s, key: %s, tags: %s',
            property_exists($event, 'storeName') ? $event->storeName : null,
            $event->key,
            implode(',', $event->tags)
        ));
    }
}
