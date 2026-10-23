<?php

namespace HughCube\Laravel\Knight\Scheduling;

use HughCube\Laravel\Knight\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\View\Components\Factory as ComponentFactory;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Runtime;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * 常驻内存调度守护命令
 *
 * =========================================================================
 * 【核心设计思路】
 * =========================================================================
 * 1. 零配置开箱即用（Zero-Config Philosophy）：
 *    无需繁琐的配置文件或环境变量，默认自动识别环境驱动（有 Swoole 用 Swoole，无 Swoole 自动降级 Native），
 *    内置经过生产检验的最佳工程参数（默认并发 4、超时 60s、内存 128MB）。
 *
 * 2. 100% 官方视觉体验与交互：
 *    启动 Banner 输出、点号进度条、耗时与退出状态 100% 像素级对齐官方 schedule:work / schedule:run 规范。
 *
 * 3. 操作系统信号优雅平滑停机：
 *    统一注册 POSIX 信号监听 (SIGTERM / SIGINT / SIGQUIT)，收到退出信号时平滑排空在途任务后安全退出。
 */
class ScheduleDaemonCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schedule:daemon
        {--driver=auto : 驱动模式 [auto: 自动识别 | swoole: 强制 Swoole 协程 | native: 强制原生循环]}
        {--concurrency=4 : 最大并发任务数(仅 Swoole 驱动有效)}
        {--timeout=60 : 单任务硬超时保护(秒)}
        {--max-memory=128 : 内存超限自动退出重启阈值(MB)}
        {--max-runs=0 : 最大执行轮数，达到后优雅退出由容器重启(0为不限)}
        {--interval=200 : 原生驱动的轮询检测间隔(ms)}
        {--immediate : 启动后首轮无需等待整点，立即触发执行}
        {--whisper : 当没有任务需要执行时不输出提示信息}
        {--run-output-file= : 输出重定向写入目标日志文件(兼容官方 schedule:work)}';

    /**
     * @var string
     */
    protected $description = '常驻内存调度守护进程（100% 官方视觉体验，消除 CPU 锯齿冷启动毛刺，支持 Swoole 协程受控并发与原生零依赖顺序双模式）';

    /**
     * 执行命令
     */
    public function handle(Schedule $schedule): int
    {
        $outputFile = $this->option('run-output-file');
        if (!empty($outputFile)) {
            $dir = dirname((string) $outputFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $stream = fopen((string) $outputFile, 'a');
            if ($stream !== false) {
                $streamOutput = new StreamOutput($stream);
                $this->output = new OutputStyle(new ArrayInput([]), $streamOutput);
                if (class_exists('Illuminate\Console\View\Components\Factory')) {
                    $componentsClass = 'Illuminate\Console\View\Components\Factory';
                    $this->components = new $componentsClass($this->output);
                }
            }
        }

        $components = property_exists($this, 'components') ? $this->components : null;
        $runner = new TaskRunner($this->laravel, $this->output, $components);

        $verbosity = $this->getLaravel()->environment('local')
            ? OutputInterface::VERBOSITY_NORMAL
            : OutputInterface::VERBOSITY_VERBOSE;
        $runner->components()->info('Running scheduled tasks.', $verbosity);

        $driverName = $this->resolveDriverName();
        $driver = $this->createDriver($driverName);

        // 统一注册操作系统信号平滑停机（根据实际驱动精准接入，防 Native 模式下 Swoole 信号监听失效）
        $this->registerSignalHandler($driver, $driverName);

        return $driver->run($schedule, $runner, [
            'concurrency' => $this->option('concurrency'),
            'timeout'    => $this->option('timeout'),
            'max_memory' => $this->option('max-memory'),
            'max_runs'   => $this->option('max-runs'),
            'interval'   => $this->option('interval'),
            'immediate'  => (bool) $this->option('immediate'),
            'whisper'    => (bool) $this->option('whisper'),
            'run_output_file' => $outputFile,
        ]);
    }

    /**
     * 解析驱动名称 (auto | swoole | native)
     */
    protected function resolveDriverName(): string
    {
        $driver = strtolower((string) ($this->option('driver') ?: 'auto'));

        if ($driver === 'swoole') {
            if (!$this->isSwooleAvailable()) {
                throw new RuntimeException('Swoole extension is not loaded or Coroutine is not available.');
            }
            return 'swoole';
        }

        if ($driver === 'native') {
            return 'native';
        }

        // 默认 auto：自动探测当前运行环境
        return $this->isSwooleAvailable() ? 'swoole' : 'native';
    }

    /**
     * 当前环境是否支持 Swoole 协程
     */
    protected function isSwooleAvailable(): bool
    {
        return extension_loaded('swoole')
            && class_exists(Coroutine::class)
            && class_exists(Runtime::class)
            && method_exists(Runtime::class, 'enableCoroutine');
    }

    /**
     * 工厂方法创建驱动实例
     */
    protected function createDriver(string $driverName): DriverInterface
    {
        if ($driverName === 'swoole') {
            return new SwooleDriver($this->output);
        }

        return new NativeDriver($this->output);
    }

    /**
     * 注册系统信号监听
     */
    protected function registerSignalHandler(DriverInterface $driver, string $driverName): void
    {
        // Swoole 协程驱动优先接入 Swoole 原生进程信号处理器
        $isSwooleSignal = $driverName === 'swoole'
            && class_exists(\Swoole\Process::class)
            && method_exists(\Swoole\Process::class, 'signal');
        if ($isSwooleSignal) {
            foreach (['SIGTERM', 'SIGINT', 'SIGQUIT'] as $sigName) {
                if (defined($sigName)) {
                    \Swoole\Process::signal(constant($sigName), function () use ($driver) {
                        if (method_exists($driver, 'stop')) {
                            $driver->stop();
                        }
                    });
                }
            }
        } elseif (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            // 原生 CLI 驱动严格接入 PCNTL 原生信号监听，彻底避免因存在 Swoole 扩展但未启动 EventLoop 导致的信号丢失
            pcntl_async_signals(true);
            foreach (['SIGTERM', 'SIGINT', 'SIGQUIT'] as $sigName) {
                if (defined($sigName)) {
                    pcntl_signal(constant($sigName), function () use ($driver) {
                        if (method_exists($driver, 'stop')) {
                            $driver->stop();
                        }
                    });
                }
            }
        }

        // Windows 平台标准 Ctrl+C / Ctrl+Break 优雅停机信号支持
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (int $event) use ($driver) {
                $ctrlC = defined('PHP_WINDOWS_EVENT_CTRL_C') ? constant('PHP_WINDOWS_EVENT_CTRL_C') : 0;
                $ctrlBreak = defined('PHP_WINDOWS_EVENT_CTRL_BREAK') ? constant('PHP_WINDOWS_EVENT_CTRL_BREAK') : 1;
                if ($event === $ctrlC || $event === $ctrlBreak) {
                    if (method_exists($driver, 'stop')) {
                        $driver->stop();
                    }
                }
            });
        }
    }
}
