<?php

namespace HughCube\Laravel\Knight\Scheduling\Concerns;

use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 调度事件生命周期与排他互斥核心能力 Trait.
 *
 * 为 CommandJob 与 TaskRunner 提供统一的：
 * 1. 互斥排他锁检测与安全释放 (withoutOverlapping)
 * 2. 官方调度生命周期 4 大事件派发 (Starting/Finished/Failed/Skipped)
 * 3. 前后置钩子触发 (callBeforeCallbacks / callAfterCallbacks)
 * 4. 退出码赋值与输出重定向落盘 (sendOutputTo / appendOutputTo)
 * 5. 残留事务安全回滚 (保留长连接，严禁 disconnect)
 */
trait ManagesEventLifecycle
{
    /**
     * 判断事件是否因重叠而应当跳过 (防并发踩踏).
     *
     * @param  Event  $event
     * @return bool
     */
    public function shouldSkipDueToOverlapping(Event $event): bool
    {
        if (method_exists($event, 'shouldSkipDueToOverlapping')) {
            return $event->shouldSkipDueToOverlapping();
        }

        return (bool) $event->withoutOverlapping && ! $event->mutex->create($event);
    }

    /**
     * 安全释放任务互斥锁 (withoutOverlapping).
     *
     * @param  Event  $event
     */
    public function releaseMutexSafely(Event $event): void
    {
        if (! empty($event->withoutOverlapping)) {
            try {
                $event->mutex->forget($event);
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * 写入任务标准输出与日志文件 (sendOutputTo / appendOutputTo).
     *
     * @param  Event  $event
     * @param  string  $output
     */
    public function writeEventOutput(Event $event, string $output): void
    {
        if (empty($output) || empty($event->output) || $event->output === $event->getDefaultOutput()) {
            return;
        }

        try {
            $dir = dirname($event->output);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            file_put_contents(
                $event->output,
                $output,
                $event->shouldAppendOutput ? FILE_APPEND : 0
            );
        } catch (Throwable $e) {
        }
    }

    /**
     * 执行具体命令并捕获标准输出与退出码.
     *
     * 涵盖：
     * A. 闭包事件 (CallbackEvent)
     * B. Artisan 命令 (同进程内存直驱调用，免 Fork 进程)
     * C. 外部 Shell 系统命令 (通过 Symfony Process 运行)
     *
     * @param  Event  $event
     * @param  Container  $container
     * @return int 退出码 (0: 成功, 非0: 失败)
     *
     * @throws Throwable
     */
    protected function runCommandSafely(Event $event, Container $container): int
    {
        if ($event instanceof CallbackEvent) {
            // A. 原生闭包回调：原生 run() 内部自洽处理抢锁、before、执行、after 与解锁
            ob_start();
            $failedException = null;
            try {
                $event->run($container);
            } catch (Throwable $e) {
                $failedException = $e;
            } finally {
                $rawOutput = ob_get_clean() ?: '';
            }

            if (! empty($rawOutput)) {
                $this->writeEventOutput($event, $rawOutput);
            }

            if ($failedException !== null) {
                throw $failedException;
            }

            // 官方原生 CallbackEvent 在 finish() 中已根据 ($result === false ? 1 : 0) 正确赋值 exitCode
            return isset($event->exitCode) ? (int) $event->exitCode : 0;
        }

        // B & C. Artisan 命令与外部 Shell 命令由我们统一管控生命周期
        $event->callBeforeCallbacks($container);

        $exitCode = 0;
        $capturedOutput = '';
        $failedException = null;

        try {
            if ($this->shouldRunInCurrentProcess($event)) {
                // B. 纯正 Artisan 命令：在当前进程内存直驱，零进程开销
                $artisanCommand = $this->extractArtisanCommand($event->command);
                $kernel = $container->make(ConsoleKernel::class);
                $buffer = new BufferedOutput();

                ob_start();
                try {
                    $exitCode = (int) $kernel->call($artisanCommand, [], $buffer);
                } catch (Throwable $e) {
                    $failedException = $e;
                    $exitCode = 1;
                } finally {
                    $rawOutput = ob_get_clean() ?: '';
                }

                $capturedOutput = $buffer->fetch();
                if (! empty($rawOutput)) {
                    $capturedOutput = ($capturedOutput !== '' ? $capturedOutput."\n" : '').$rawOutput;
                }
            } else {
                // C. 外部系统命令 (Schedule::exec)
                $process = Process::fromShellCommandline((string) $event->command);
                $exitCode = $process->run();
                $capturedOutput = $process->getOutput().$process->getErrorOutput();
            }
        } catch (Throwable $e) {
            $failedException = $e;
            $exitCode = 1;
        }

        // 2. 写入重定向文件
        if (! empty($capturedOutput)) {
            $this->writeEventOutput($event, $capturedOutput);
        }

        // 3. 退出码赋值 (必须先于 callAfterCallbacks 赋值，确保 onSuccess / onFailure 回调精准判定)
        $event->exitCode = $exitCode;

        // 4. 触发后置钩子 (after / onSuccess / onFailure)
        $event->callAfterCallbacks($container);

        if ($failedException !== null) {
            throw $failedException;
        }

        return $exitCode;
    }

    /**
     * 判断是否可以在当前进程中内存直驱执行.
     *
     * 仅当命令为纯正 Artisan 命令且不包含任何 Shell 管道 (|)、重定向 (><) 或后台符号 (&) 时，
     * 才能在当前进程内存调用；否则必须降级为 Symfony Process 启动子进程交由系统 Shell 处理。
     */
    protected function shouldRunInCurrentProcess(Event $event): bool
    {
        if (empty($event->command) || ! $this->isArtisanCommand((string) $event->command)) {
            return false;
        }

        return preg_match('/[|><&;`]|(?:\$\()/', (string) $event->command) !== 1;
    }

    /**
     * 判断是否为 Artisan 命令.
     */
    protected function isArtisanCommand(string $command): bool
    {
        return preg_match(
            '/(?:(?:"[^"]*artisan[^"]*"|\'[^\']*artisan[^\']*\'|\S*artisan\S*))/i',
            $command
        ) === 1;
    }

    /**
     * 从完整命令行中提取纯净的 artisan 命令名与参数.
     *
     * 严谨移除非命令开头的 php 与 artisan 二进制路径，绝不误伤参数内部包含的同名词汇.
     */
    protected function extractArtisanCommand(string $command): string
    {
        $prefix = sprintf('%s %s ', Application::phpBinary(), Application::artisanBinary());
        if (str_starts_with($command, $prefix)) {
            return trim(substr($command, strlen($prefix)));
        }

        $cleaned = preg_replace(
            '/^(?:(?:"[^"]*php[^"]*"|\'[^\']*php[^\']*\'|\S*php\S*)\s+)?'
            .'(?:"[^"]*artisan[^"]*"|\'[^\']*artisan[^\']*\'|\S*artisan\S*)\s+/i',
            '',
            trim($command)
        );

        return trim((string) $cleaned);
    }

    /**
     * 重置与清理常驻内存环境状态 (不断开长连接，保护 Octane / Swoole 连接池).
     *
     * 1. 回滚未提交的事务至 Level 0；
     * 2. 清空数据库查询日志，防止常驻内存下无限增长导致 OOM；
     * 3. 忘记残留的用户身份认证态 (forgetGuards)，防止跨任务身份串扰。
     *
     * @param  Container  $container
     */
    public function resetRuntimeState(Container $container): void
    {
        // 1. 回滚事务与清空 QueryLog
        if (class_exists(DB::class)) {
            try {
                /** @var array<string, mixed> $connections */
                $connections = DB::getConnections();
                foreach ($connections as $connection) {
                    if ($connection instanceof \Illuminate\Database\Connection) {
                        if ($connection->transactionLevel() > 0) {
                            try {
                                $connection->rollBack(0);
                            } catch (Throwable $e) {
                            }
                        }
                        try {
                            $connection->flushQueryLog();
                        } catch (Throwable $e) {
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        // 2. 清理残留认证 Guard，防止跨任务身份污染
        if ($container->bound('auth')) {
            try {
                $auth = $container->make('auth');
                if (method_exists($auth, 'forgetGuards')) {
                    $auth->forgetGuards();
                }
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * 回滚所有连接上未提交的事务 (兼容旧方法名).
     *
     * @param  Container  $container
     */
    public function rollbackUncommittedTransactions(Container $container): void
    {
        $this->resetRuntimeState($container);
    }
}
