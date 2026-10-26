<?php

use HughCube\Laravel\Knight\OPcache\OPcache;
use Illuminate\Support\Collection;
use PhpParser\ParserFactory;

$basePath = getcwd();
$preloadFile = getenv('OUTPUT_PATH') ?: $basePath.'/preload.php';
$skipBootstrap = '1' === getenv('SKIP_BOOTSTRAP');

// 用户可以通过 preload-config.php 自定义排除规则
$excludes = [];

// 加载用户自定义配置
$configFile = $basePath.'/preload-config.php';
if (is_file($configFile)) {
    $userConfig = require $configFile;
    if (isset($userConfig['exclude_classes'])) {
        $excludes = array_merge($excludes, $userConfig['exclude_classes']);
    }
    echo 'Loaded custom preload config', PHP_EOL;
}

$classes = array_merge(
    get_declared_classes(),
    get_declared_interfaces(),
    get_declared_traits()
);

call_user_func(function () {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? null ?: 'localhost';
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? null ?: '127.0.0.1';
});

/** Bootstrap Laravel Application */
call_user_func(function () use ($basePath, $skipBootstrap) {
    if ($skipBootstrap) {
        echo 'Skipping Laravel application bootstrap...', PHP_EOL;

        return;
    }

    echo 'Bootstrapping Laravel application...', PHP_EOL;

    // 查找 public/index.php 或直接使用 bootstrap
    $indexFile = $basePath.'/public/index.php';
    if (! is_file($indexFile)) {
        // Lumen 或其他结构
        $indexFile = $basePath.'/index.php';
    }

    if (is_file($indexFile)) {
        ob_start();
        require $indexFile;
        ob_clean();
        echo 'Application bootstrapped successfully.', PHP_EOL;
    } else {
        fwrite(STDERR, 'Warning: index.php not found, skipping bootstrap.'.PHP_EOL);
    }
});

/** Load files from various sources */
call_user_func(function () use ($basePath) {
    $filesToLoad = Collection::make();

    // 1. Remote Scripts
    if ('1' === getenv('WITH_REMOTE_SCRIPTS')) {
        echo 'Loading remote scripts...', PHP_EOL;

        try {
            $opcache = OPcache::i();
            $remoteScripts = $opcache->getRemoteScripts();
            foreach ($remoteScripts as $script) {
                $filesToLoad->push(base_path($script));
            }
            echo sprintf('  Added %d remote scripts', count($remoteScripts)), PHP_EOL;
        } catch (Throwable $e) {
            fwrite(STDERR, 'Warning: Failed to fetch remote scripts: '.$e->getMessage().PHP_EOL);
        }
    }

    // Parse and load all classes from collected files
    if ($filesToLoad->isNotEmpty()) {
        $uniqueFiles = $filesToLoad->unique();
        echo sprintf('Parsing %d unique files to extract classes...', $uniqueFiles->count()), PHP_EOL;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $opcache = OPcache::i();
        $loadedClasses = 0;
        $failedFiles = 0;
        $skippedFiles = 0;

        foreach ($uniqueFiles as $file) {
            try {
                if (! is_file($file)) {
                    $skippedFiles++;
                    continue;
                }

                $content = @file_get_contents($file);
                if (empty($content)) {
                    $skippedFiles++;
                    continue;
                }

                if (stripos($content, 'class ') === false && stripos($content, 'interface ') === false && stripos($content, 'trait ') === false) {
                    $skippedFiles++;
                    continue;
                }

                $stmts = $parser->parse($content);
                if (! $stmts) {
                    $skippedFiles++;
                    continue;
                }

                $classes = $opcache->getPHPParserStmtClasses($stmts);
                if ($classes->isEmpty()) {
                    $skippedFiles++;
                    continue;
                }

                foreach ($classes as $class) {
                    try {
                        if (class_exists($class, true) || interface_exists($class, true) || trait_exists($class, true)) {
                            $loadedClasses++;
                        }
                    } catch (Throwable $classError) {
                        fwrite(STDERR, sprintf("Warning: Failed to load class '%s': %s", $class, $classError->getMessage()).PHP_EOL);
                    }
                }
            } catch (\PhpParser\Error $parseError) {
                $failedFiles++;
                $relativeFile = str_replace($basePath.'/', '', $file);
                fwrite(STDERR, sprintf("Warning: Parse error in '%s': %s", $relativeFile, $parseError->getMessage()).PHP_EOL);
            } catch (Throwable $e) {
                $failedFiles++;
            }
        }

        echo sprintf('  Successfully loaded %d classes/interfaces/traits', $loadedClasses), PHP_EOL;
        if ($skippedFiles > 0) {
            echo sprintf('  Skipped %d files (no classes or empty)', $skippedFiles), PHP_EOL;
        }
        if ($failedFiles > 0) {
            echo sprintf('  Failed to parse %d files (syntax errors)', $failedFiles), PHP_EOL;
        }
    }
});

$loads = Collection::empty()
    ->merge(get_declared_classes())
    ->merge(get_declared_interfaces())
    ->merge(get_declared_traits())
    /** 剔除系统类 */
    ->diff($classes)
    ->diff($excludes)
    /** 剔除PhpParser */
    ->filter(function ($class) {
        return 0 !== strripos($class, 'PhpParser\\');
    })
    /** 剔除Laravel别名类 */
    ->filter(function ($class) {
        return 0 < strripos($class, '\\');
    })
    ->values()->map(function ($class) {
        $reflection = new \ReflectionClass($class);

        // 获取文件路径(相对于 base_path)
        $file = $reflection->getFileName();
        $relativeFile = $file ? str_replace(base_path().'/', '', $file) : 'unknown';

        return [
            'class' => $class,
            'file' => $relativeFile,
            'is_interface' => $reflection->isInterface(),
            'is_trait' => $reflection->isTrait(),
        ];
    })
    /** 按类型排序: Interface -> Trait -> Class */
    ->sort(function ($a, $b) {
        $getSort = function ($item) {
            if ($item['is_interface']) {
                return 1;
            } elseif ($item['is_trait']) {
                return 2;
            } else {
                return 3;
            }
        };

        return $getSort($a) <=> $getSort($b);
    })->values();

// 统计信息
$stats = [
    'interfaces' => $loads->filter(fn ($i) => $i['is_interface'])->count(),
    'traits' => $loads->filter(fn ($i) => $i['is_trait'])->count(),
    'classes' => $loads->filter(fn ($i) => ! $i['is_interface'] && ! $i['is_trait'])->count(),
];

echo sprintf('Collected %d items: %d interfaces, %d traits, %d classes', $loads->count(), $stats['interfaces'], $stats['traits'], $stats['classes']), PHP_EOL;

// 生成 preload 文件内容
$contents = "<?php\n";
$contents .= "/**\n";
$contents .= " * OPcache Preload File\n";
$contents .= ' * Generated at: '.date('Y-m-d H:i:s')."\n";
$contents .= ' * Total items: '.$loads->count()."\n";
$contents .= ' *   - Interfaces: '.$stats['interfaces']."\n";
$contents .= ' *   - Traits: '.$stats['traits']."\n";
$contents .= ' *   - Classes: '.$stats['classes']."\n";
$contents .= " */\n\n";
$contents .= "require_once __DIR__.'/vendor/autoload.php';\n\n";

// 生成加载语句
foreach ($loads as $item) {
    $class = $item['class'];
    if ($item['is_interface']) {
        $contents .= "interface_exists('{$class}', true);\n";
    } elseif ($item['is_trait']) {
        $contents .= "trait_exists('{$class}', true);\n";
    } else {
        $contents .= "class_exists('{$class}', true);\n";
    }
}

if (file_put_contents($preloadFile, $contents) <= 0) {
    throw new Exception('Unable to write preload file');
}

echo sprintf('Preload file created: %s', $preloadFile), PHP_EOL;
echo sprintf('Total preloaded items: %d', $loads->count()), PHP_EOL;
echo PHP_EOL;
echo 'To use this preload file, add the following to your php.ini:', PHP_EOL;
echo sprintf('  opcache.preload=%s', $preloadFile), PHP_EOL;
