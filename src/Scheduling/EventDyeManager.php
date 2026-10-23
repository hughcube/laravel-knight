<?php

namespace HughCube\Laravel\Knight\Scheduling;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;
use WeakMap;

/**
 * 调度事件染色元数据管理器 (WeakMap 内存安全).
 *
 * 用于流式标记哪些任务需要推入异步队列 (runInQueue)，
 * 并兼容原生 runInBackground 以及基于配置通配符的老项目无侵入适配。
 */
class EventDyeManager
{
    /**
     * @var WeakMap<Event, array>|null
     */
    protected static ?WeakMap $dyes = null;

    /**
     * 将调度事件染色为异步队列任务.
     *
     * @param Event       $event      目标事件
     * @param bool        $inQueue    是否推入队列运行
     * @param string|null $queue      指定的队列通道名称
     * @param string|null $connection 指定的队列连接名称
     */
    public static function dye(Event $event, bool $inQueue = true, ?string $queue = null, ?string $connection = null): void
    {
        self::$dyes ??= new WeakMap();
        self::$dyes[$event] = [
            'in_queue'   => $inQueue,
            'queue'      => $queue,
            'connection' => $connection,
        ];
    }

    /**
     * 判断事件是否应当由异步队列消费.
     *
     * 判定优先级:
     * 1. 显式调用 ->runInQueue() / ->onQueue() / ->queued() 染色
     * 2. 兼容 Laravel 原生标记 ->runInBackground()
     * 3. 兼容老项目配置规则 config('knight.schedule.heavy_commands', [])
     *
     * @param Event $event
     * @return bool
     */
    public static function shouldRunInQueue(Event $event): bool
    {
        // 1. 显式流式染色
        if (isset(self::$dyes[$event])) {
            return (bool) self::$dyes[$event]['in_queue'];
        }

        // 2. 原生 runInBackground 标记
        if (!empty($event->runInBackground)) {
            return true;
        }

        // 3. 配置通配符匹配 (免改动老项目代码)
        $patterns = config('knight.schedule.heavy_commands', []);
        if (!empty($patterns) && is_array($patterns) && !empty($event->command)) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $event->command)
                    || Str::is("* {$pattern}*", $event->command)
                    || Str::is("* {$pattern}", $event->command)
                    || Str::is("*'{$pattern}'*", $event->command)
                    || Str::is("*\"{$pattern}\"*", $event->command)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取队列派发参数配置.
     *
     * @param Event $event
     * @return array{queue: ?string, connection: ?string}
     */
    public static function getQueueOptions(Event $event): array
    {
        if (isset(self::$dyes[$event])) {
            return [
                'queue'      => self::$dyes[$event]['queue'],
                'connection' => self::$dyes[$event]['connection'],
            ];
        }

        return [
            'queue'      => null,
            'connection' => null,
        ];
    }

    /**
     * 清空染色缓存（主要供单测重置环境使用）.
     */
    public static function flush(): void
    {
        self::$dyes = null;
    }
}
