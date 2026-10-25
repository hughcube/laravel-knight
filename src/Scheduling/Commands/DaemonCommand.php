<?php

namespace HughCube\Laravel\Knight\Scheduling\Commands;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Scheduling\ScheduleRunner;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

/**
 * 常驻内存调度守护命令.
 *
 * 在当前进程中直接驱动 schedule:run 执行调度任务，消灭子进程冷启动与框架重复加载开销。
 * 1. 毫秒级整点对齐检测；
 * 2. 100% 官方视觉体验与输出重定向；
 * 3. 内存超限保护与操作系统平滑停机信号支持。
 */
class DaemonCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schedule:daemon
        {--max-memory=128 : 内存超限自动优雅退出阈值(MB)}
        {--max-runs=0 : 最大执行轮数，达到后优雅退出(0为不限)}
        {--interval=100 : 轮询检测间隔(ms)}
        {--immediate : 启动后首轮无需等待整点，立即触发执行}
        {--whisper : 当没有任务需要执行时不输出提示信息}
        {--run-output-file= : 输出重定向写入目标日志文件(兼容官方 schedule:work)}';

    /**
     * @var string
     */
    protected $description = '常驻内存调度守护进程（同进程极速直驱调度，消灭子进程冷启动与框架加载开销）';

    /**
     * @var bool 是否收到停止信号
     */
    public bool $shouldQuit = false;

    /**
     * @var int 已执行轮数
     */
    protected int $runs = 0;

    /**
     * @var resource|null 重定向文件句柄
     */
    protected $outputStream = null;

    /**
     * 执行命令.
     */
    public function handle(): int
    {
        $this->setupOutput();

        $verbosity = $this->getLaravel()->environment('local')
            ? OutputInterface::VERBOSITY_NORMAL
            : OutputInterface::VERBOSITY_VERBOSE;

        $components = $this->getComponents();
        if ($components !== null) {
            $components->info('Running scheduled tasks in daemon mode.', $verbosity);
        } else {
            $this->info('Running scheduled tasks in daemon mode.');
        }

        $this->listenForSignals();

        return $this->work();
    }

    /**
     * 运行守护进程主循环.
     */
    protected function work(): int
    {
        $immediate = (bool) $this->option('immediate');
        $lastExecutionStartedAt = $immediate
            ? Carbon::now()->subMinutes(10)
            : Carbon::now()->startOfMinute();
        $maxRuns = (int) $this->option('max-runs');
        $maxMemory = (int) $this->option('max-memory');
        $interval = max(10, (int) ($this->option('interval') ?: 100));

        while (! $this->shouldQuit) {
            $now = Carbon::now();
            $isDue = ($immediate && $this->runs === 0)
                || $now->startOfMinute()->greaterThan($lastExecutionStartedAt);

            if ($isDue) {
                $lastExecutionStartedAt = $now->startOfMinute();
                $this->runs++;

                $this->runTasks();

                if ($maxRuns > 0 && $this->runs >= $maxRuns) {
                    break;
                }

                if ($maxMemory > 0 && (memory_get_usage(true) / 1024 / 1024) >= $maxMemory) {
                    $components = $this->getComponents();
                    if ($components !== null) {
                        $components->warn('Memory limit exceeded, stopping schedule daemon...');
                    } else {
                        $this->warn('Memory limit exceeded, stopping schedule daemon...');
                    }
                    break;
                }
            }

            $this->sleep($interval);
        }

        $components = $this->getComponents();
        if ($components !== null) {
            $components->info('Schedule daemon successfully stopped.');
        } else {
            $this->info('Schedule daemon successfully stopped.');
        }

        if (is_resource($this->outputStream)) {
            fclose($this->outputStream);
            $this->outputStream = null;
        }

        return static::SUCCESS;
    }

    /**
     * 触发一轮调度任务执行.
     */
    protected function runTasks(): void
    {
        $runner = new ScheduleRunner($this->laravel);
        $whisper = (bool) $this->option('whisper');

        $components = $this->getComponents();

        $outputCallback = function (Event $event, string $mode, float $duration) use ($components) {
            $description = sprintf(
                '<fg=gray>%s</> Running [%s]%s',
                Carbon::now()->format('Y-m-d H:i:s'),
                $event->getSummaryForDisplay(),
                $mode === 'background' ? ' in background' : ''
            );

            if ($mode === 'error') {
                $statusText = $components !== null ? '<fg=red;options=bold>FAIL</>' : 'FAIL';
            } elseif ($mode === 'skipped') {
                $statusText = $components !== null ? '<fg=yellow;options=bold>SKIPPED</>' : 'SKIPPED';
            } else {
                $statusText = sprintf('%.2fs DONE', $duration);
            }

            if ($components !== null) {
                $components->twoColumnDetail($description, $statusText);
            } else {
                $this->line(sprintf('%s (%s)', strip_tags($description), $statusText));
            }
        };

        try {
            $report = $runner->run(false, $outputCallback, true, fn () => $this->shouldQuit);

            if ($report['count'] === 0 && ! $whisper) {
                if ($components !== null) {
                    $components->info('No scheduled commands are ready to run.');
                } else {
                    $this->line('No scheduled commands are ready to run.');
                }
            }
        } catch (Throwable $e) {
            if ($components !== null) {
                $components->error(sprintf('Schedule execution failed: %s', $e->getMessage()));
            } else {
                $this->error(sprintf('Schedule execution failed: %s', $e->getMessage()));
            }
        } finally {
            if (is_resource($this->outputStream)) {
                @fflush($this->outputStream);
            }
        }
    }

    /**
     * @return mixed
     */
    protected function getComponents()
    {
        return property_exists($this, 'components') ? $this->components : null;
    }

    /**
     * 设置输出重定向.
     */
    protected function setupOutput(): void
    {
        $outputFile = $this->option('run-output-file');
        if (empty($outputFile)) {
            return;
        }

        $dir = dirname((string) $outputFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $stream = fopen((string) $outputFile, 'a');
        if ($stream !== false) {
            $this->outputStream = $stream;
            $streamOutput = new StreamOutput($stream);
            $this->output = new OutputStyle(new ArrayInput([]), $streamOutput);
            if (class_exists('Illuminate\Console\View\Components\Factory')) {
                $componentsClass = 'Illuminate\Console\View\Components\Factory';
                $this->components = new $componentsClass($this->output);
            }
        }
    }

    /**
     * 注册系统信号监听.
     */
    protected function listenForSignals(): void
    {
        if (method_exists($this, 'trap')) {
            $this->trap(fn () => [SIGINT, SIGTERM, SIGQUIT], function () {
                $this->stop();
            });
        } elseif (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach (['SIGTERM', 'SIGINT', 'SIGQUIT'] as $sigName) {
                if (defined($sigName)) {
                    pcntl_signal(constant($sigName), function () {
                        $this->stop();
                    });
                }
            }
        }

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (int $event) {
                $ctrlC = defined('PHP_WINDOWS_EVENT_CTRL_C') ? constant('PHP_WINDOWS_EVENT_CTRL_C') : 0;
                $ctrlBreak = defined('PHP_WINDOWS_EVENT_CTRL_BREAK') ? constant('PHP_WINDOWS_EVENT_CTRL_BREAK') : 1;
                if ($event === $ctrlC || $event === $ctrlBreak) {
                    $this->stop();
                }
            });
        }
    }

    /**
     * 停止守护循环.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * 休眠指定毫秒数.
     */
    protected function sleep(int $intervalMs): void
    {
        usleep($intervalMs * 1000);
    }
}
