<?php

namespace HughCube\Laravel\Knight\Scheduling;

use Illuminate\Console\Scheduling\Schedule;

/**
 * 常驻内存调度循环驱动契约接口.
 *
 * 【设计思路】
 * 1. 策略模式解耦：将底层常驻事件循环与上层调度任务执行逻辑（TaskRunner）完全解耦；
 * 2. 多驱动支持：提供基于 Swoole 协程的受控并发驱动（SwooleDriver）与基于纯 PHP CLI 的零依赖顺序驱动（NativeDriver）；
 * 3. 统一生命周期契约：所有驱动必须实现运行循环（run）与平滑停机中断（stop）标准接口。
 *
 * 【适用场景】
 * - 在常驻内存守护进程中持续监听时间跨度，准时唤醒到期的调度任务；
 * - 接收操作系统终止信号（SIGTERM / SIGINT）并安全收敛退出。
 *
 * 【注意事项】
 * - 实现类必须保证非阻塞时钟轮询（使用 sleep 或 coroutine::sleep 让渡 CPU，避免死循环空转打满 CPU）；
 * - 必须妥善处理在途运行任务的优雅停机（Graceful Drain），防止停机时强制腰斩未完成的业务事务。
 */
interface DriverInterface
{
    /**
     * 启动调度常驻事件循环.
     *
     * @param Schedule   $schedule Laravel 原生调度器实例
     * @param TaskRunner $runner   调度任务沙箱执行器
     * @param array      $options  运行参数选项（包含并发度、超时熔断、内存阈值、最大轮数等）
     *
     * @return int 退出状态码 (0: SUCCESS)
     */
    public function run(Schedule $schedule, TaskRunner $runner, array $options): int;

    /**
     * 接收外部中断信号，触发平滑停止.
     */
    public function stop(): void;
}
