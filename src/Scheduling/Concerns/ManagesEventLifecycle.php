<?php

namespace HughCube\Laravel\Knight\Scheduling\Concerns;

use Illuminate\Console\Application;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 调度事件生命周期与排他互斥核心能力 Trait.
 *
 * 为 QueuedCommandJob 与 TaskRunner 提供统一的：
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
     * @param Event $event
     * @return bool
     */
    public function shouldSkipDueToOverlapping(Event $event): bool
    {
        if (method_exists($event, 'shouldSkipDueToOverlapping')) {
            return $event->shouldSkipDueToOverlapping();
        }

        return (bool) $event->withoutOverlapping && !$event->mutex->create($event);
    }

    /**
     * 安全释放任务互斥锁 (withoutOverlapping).
     *
     * @param Event $event
     */
    public function releaseMutexSafely(Event $event): void
    {
        if (!empty($event->withoutOverlapping)) {
            try {
                $event->mutex->forget($event);
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * 写入任务标准输出与日志文件 (sendOutputTo / appendOutputTo).
     *
     * @param Event  $event
     * @param string $output
     */
    public function writeEventOutput(Event $event, string $output): void
    {
        if (empty($output) || empty($event->output) || $event->output === $event->getDefaultOutput()) {
            return;
        }

        try {
            $dir = dirname($event->output);
            if (!is_dir($dir)) {
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
     * @param Event     $event
     * @param Container $container
     * @return int 退出码 (0: 成功, 非0: 失败)
     * @throws Throwable
     */
    protected function runCommandSafely(Event $event, Container $container): int
    {
        // 1. 触发前置钩子 (before)
        $event->callBeforeCallbacks($container);

        $exitCode = 0;
        $capturedOutput = '';
        $failedException = null;

        try {
            if ($event instanceof CallbackEvent) {
                // A. 原生闭包回调
                $exitCode = (int) $event->run($container);
            } elseif (!empty($event->command) && $this->isArtisanCommand($event->command)) {
                // B. Artisan 命令：内存直接调用，零子进程开销
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
                if (!empty($rawOutput)) {
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
        if (!empty($capturedOutput)) {
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
     * 判断是否为 Artisan 命令.
     */
    protected function isArtisanCommand(string $command): bool
    {
        return str_contains($command, 'artisan');
    }

    /**
     * 从完整命令行中提取纯净的 artisan 命令名与参数.
     */
    protected function extractArtisanCommand(string $command): string
    {
        $phpBinary = Application::phpBinary();
        $cleaned = trim(str_replace([$phpBinary, "'artisan'", '"artisan"', 'artisan'], '', $command));

        return trim($cleaned, " '\"");
    }

    /**
     * 仅当任务发生异常退出且残留了未提交事务时，执行安全回滚，避免锁表。
     * 绝不调用 disconnect()，完整保留长连接供后续复用。
     *
     * @param Container $container
     */
    protected function rollbackUncommittedTransactions(Container $container): void
    {
        if (!$container->bound('db')) {
            return;
        }

        try {
            $db = $container->make('db');
            if (method_exists($db, 'getConnections')) {
                foreach ($db->getConnections() as $connection) {
                    try {
                        while ($connection->transactionLevel() > 0) {
                            $connection->rollBack();
                        }
                    } catch (Throwable $e) {
                    }
                }
            }
        } catch (Throwable $e) {
        }
    }
}
