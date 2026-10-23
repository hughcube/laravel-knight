<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/2/22
 * Time: 11:18.
 */

namespace HughCube\Laravel\Knight\OPcache\Commands;

use Exception;
use HughCube\GuzzleHttp\HttpClientTrait;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\OPcache\OPcache;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpProcess;
use Throwable;

class CompileFilesCommand extends Command
{
    use HttpClientTrait;
    use LoadedOPcacheExtension;

    /**
     * @inheritdoc
     */
    protected $signature = 'opcache:compile-files
                            {--with-remote-scripts : Fetch file list from remote interface }
                            {--with-app-files=1 : Include app files (default: 1) }
                            {--with-composer-files=1 : Include composer class files (default: 1) }
                            {--without-vendor : Exclude vendor files when using composer files }
                            {--timeout= : The process timeout in seconds, or 0/null for no timeout (default: none) }';

    /**
     * @inheritdoc
     */
    protected $description = 'Compile PHP files into OPcache for better performance';

    /**
     * @param Schedule $schedule
     *
     * @throws Exception
     *
     * @return void
     */
    public function handle(Schedule $schedule)
    {
        $start = Carbon::now();

        // 收集各来源的文件并显示统计
        $scripts = Collection::empty()
            ->merge(
                $appFiles = Collection::make($this->getAppFiles())
                    ->filter(fn ($file) => is_file($file))
                    ->values()
            )
            ->merge(
                $composerFiles = Collection::make($this->getComposerFiles())
                    ->filter(fn ($file) => is_file($file))
                    ->values()
            )
            ->merge(
                $prodFiles = Collection::make($this->getProdFiles())
                    ->filter(fn ($file) => is_file($file))
                    ->values()
            )
            ->merge(
                $vendorBinFiles = Collection::make($this->getVendorBinFiles())
                    ->filter(fn ($file) => is_file($file))
                    ->values()
            )
            ->merge(
                $octaneFiles = Collection::make($this->getOctaneFiles())
                    ->filter(fn ($file) => is_file($file))
                    ->values()
            )
            ->unique()->values();

        $this->info('Collecting files to compile...');
        $this->info(sprintf('  - App files: %d', $appFiles->count()));
        $this->info(sprintf('  - Composer files: %d', $composerFiles->count()));
        if ($prodFiles->isNotEmpty()) {
            $this->info(sprintf('  - Remote cached files: %d', $prodFiles->count()));
        }
        if ($vendorBinFiles->isNotEmpty()) {
            $this->info(sprintf('  - Vendor bin files: %d', $vendorBinFiles->count()));
        }
        if ($octaneFiles->isNotEmpty()) {
            $this->info(sprintf('  - Octane files: %d', $octaneFiles->count()));
        }
        $this->info(sprintf('Total unique files to compile: %d', $scripts->count()));

        $scripts = $scripts->toArray();

        if (empty($scripts)) {
            $this->warn('No files to compile!');

            return;
        }

        $file = storage_path('opcache_compile_files.json');
        file_put_contents($file, json_encode($scripts));
        $consecutiveErrors = 0;
        $lastCount = count($scripts);
        $timeout = $this->getProcessTimeout();

        while (is_file($file)) {
            $process = new PhpProcess(sprintf('<?php %s ?>', $this->compileProcessCode($file)));
            $process->setTimeout($timeout);
            $process->start();

            try {
                $process->wait();
            } catch (Throwable $e) {
                $this->warn(sprintf('Compilation process interrupted (%s), continuing with remaining files...', $e->getMessage()));
            }

            $remainScripts = json_decode(file_get_contents($file), true) ?: [];
            if (empty($remainScripts)) {
                break;
            }

            $currentCount = count($remainScripts);
            if ($currentCount >= $lastCount) {
                $consecutiveErrors++;
                if ($consecutiveErrors >= 5) {
                    $this->error('Aborting: compile process failed to make progress after multiple attempts.');
                    break;
                }
                array_pop($remainScripts);
                file_put_contents($file, json_encode($remainScripts));
            } else {
                $consecutiveErrors = 0;
                $lastCount = $currentCount;
            }
        }
        File::delete($file);

        $end = Carbon::now();

        $this->info(sprintf(
            'Successfully compiled %s files in %.2fs',
            count($scripts),
            /** @phpstan-ignore-next-line */
            $end->getTimestampAsFloat() - $start->getTimestampAsFloat()
        ));
    }

    protected function compileProcessCode($file): string
    {
        $code = sprintf('$dataFile = "%s";', $file);
        $code .= 'while (true){';
        $code .= '    if(!is_file($dataFile)){break;}';
        $code .= '    $classmap = json_decode(file_get_contents($dataFile), true);';
        $code .= '    if(empty($classmap)){break;}';
        $code .= '    $file = array_pop($classmap);';
        $code .= '    file_put_contents($dataFile, json_encode($classmap));';
        $code .= '    if(is_file($file)){opcache_compile_file($file);};';
        $code .= '    if(is_file($file)){echo $file, PHP_EOL;}';
        $code .= '}';

        return $code;
    }

    protected function getComposerFiles(): array
    {
        if (!$this->option('with-composer-files')) {
            return [];
        }

        $files = [];

        // 加载 Composer 的 classmap
        $file = base_path('vendor/composer/autoload_classmap.php');
        if (is_file($file)) {
            $files = array_merge($files, array_values(require $file));
        }

        // 加载 Composer 的 autoload files
        $file = base_path('vendor/composer/autoload_files.php');
        if (is_file($file)) {
            $files = array_merge($files, array_values(require $file));
        }

        // 加载 PSR-4 自动加载的命名空间路径
        $file = base_path('vendor/composer/autoload_psr4.php');
        if (is_file($file)) {
            $psr4 = require $file;
            foreach ($psr4 as $paths) {
                foreach ((array) $paths as $path) {
                    if (is_dir($path)) {
                        $finder = Finder::create()
                            ->in($path)
                            ->name('*.php')
                            ->ignoreUnreadableDirs()
                            ->followLinks();

                        foreach ($finder->files() as $phpFile) {
                            $files[] = strval($phpFile);
                        }
                    }
                }
            }
        }

        // 如果设置了排除 vendor,过滤掉 vendor 目录下的文件
        if ($this->option('without-vendor')) {
            $vendorPath = base_path('vendor/');
            $files = array_filter($files, function ($file) use ($vendorPath) {
                return strpos($file, $vendorPath) !== 0;
            });
        }

        return array_values(array_filter(array_unique($files)));
    }

    /**
     * @throws Exception
     */
    protected function getAppFiles(): array
    {
        if (!$this->option('with-app-files')) {
            return [];
        }

        $directories = [];

        // 核心应用目录
        if (is_dir(base_path('app/'))) {
            $directories[] = base_path('app/');
        }
        if (is_dir(base_path('bootstrap/'))) {
            $directories[] = base_path('bootstrap/');
        }

        // 配置和路由目录(包含应用逻辑)
        if (is_dir(base_path('config/'))) {
            $directories[] = base_path('config/');
        }
        if (is_dir(base_path('routes/'))) {
            $directories[] = base_path('routes/');
        }

        // 数据库迁移和 seeders
        if (is_dir(base_path('database/'))) {
            $directories[] = base_path('database/');
        }

        if (empty($directories)) {
            return [];
        }

        $finder = Finder::create()
            ->in($directories)
            ->name('*.php')
            ->ignoreUnreadableDirs()
            ->followLinks();

        $files = [];
        foreach ($finder->files() as $file) {
            $files[] = strval($file);
        }

        return $files;
    }

    protected function getProdFiles(): array
    {
        if (empty($this->option('with-remote-scripts'))) {
            return [];
        }

        $scripts = [];

        try {
            $scripts = OPcache::i()->getRemoteScripts();
        } catch (Throwable $exception) {
            $this->warn($exception->getMessage());
        }

        return Collection::make($scripts)->map(function ($script) {
            return base_path($script);
        })->values()->toArray();
    }

    /**
     * 获取 vendor/bin 目录下的 PHP 脚本文件.
     *
     * @return array
     */
    protected function getVendorBinFiles(): array
    {
        return $this->findPhpScripts(base_path('vendor/bin/'));
    }

    /**
     * 获取 vendor/laravel/octane 目录下的所有 PHP 文件
     * 包括 bin 目录下的 PHP 脚本（即使没有 .php 后缀）.
     *
     * @return array
     */
    protected function getOctaneFiles(): array
    {
        return $this->findPhpScripts(base_path('vendor/laravel/octane/'), true);
    }

    /**
     * 查找指定目录下的所有 PHP 脚本文件
     * 包括 .php 文件和包含 PHP shebang 的可执行脚本.
     *
     * @param string $path        要搜索的目录路径
     * @param bool   $followLinks 是否跟随符号链接（默认 false）
     *
     * @return array
     */
    protected function findPhpScripts(string $path, bool $followLinks = false): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $finder = Finder::create()
            ->in($path)
            ->files()
            ->ignoreUnreadableDirs();

        if ($followLinks) {
            $finder = $finder->followLinks();
        }

        return Collection::make($finder)
            ->filter(function ($file) {
                // 允许 .php 文件
                if ($file->getExtension() === 'php') {
                    return true;
                }

                // 允许首行包含 #!/usr/bin/env php 或类似 shebang 的文件
                if ($file->isReadable() && ($handle = fopen($file->getRealPath(), 'r'))) {
                    $firstLine = fgets($handle);
                    fclose($handle);

                    return !empty($firstLine) && preg_match('/^#!.*php/', $firstLine);
                }

                return false;
            })
            ->map(fn ($file) => $file->getRealPath())->unique()->values()->all();
    }

    /**
     * 获取子进程超时时间（秒）.
     * 默认无时长限制(null), 可通过 --timeout 参数显式控制.
     *
     * @return float|null
     */
    protected function getProcessTimeout(): ?float
    {
        try {
            $timeout = $this->option('timeout');
        } catch (Throwable $e) {
            $timeout = null;
        }

        if (null === $timeout || '' === $timeout) {
            return null;
        }

        $timeout = (float) $timeout;

        return $timeout > 0 ? $timeout : null;
    }
}
