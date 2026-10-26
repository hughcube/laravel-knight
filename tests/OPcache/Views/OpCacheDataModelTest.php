<?php

namespace HughCube\Laravel\Knight\Tests\OPcache\Views;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class OpCacheDataModelTest extends TestCase
{
    public function testDataModelMethodsWithSampleData()
    {
        $this->loadOpcacheView();

        $this->assertTrue(class_exists('OpCacheDataModel'));

        $model = new \OpCacheDataModel();
        $status = $this->buildSampleStatus();
        $config = $this->buildSampleConfig();

        $this->setPrivateProperty($model, '_status', $status);
        $this->setPrivateProperty($model, '_configuration', $config);

        $this->assertStringContainsString('OpCache', $model->getPageTitle());

        $statusRows = $model->getStatusDataRows();
        $this->assertStringContainsString('used_memory', $statusRows);
        $this->assertStringContainsString('blacklist_miss_ratio', $statusRows);
        $this->assertStringContainsString('resource:', $statusRows);

        $configRows = $model->getConfigDataRows();
        $this->assertStringContainsString('opcache.memory_consumption', $configRows);

        $scriptRows = $model->getScriptStatusRows();
        $this->assertStringContainsString('clickable', $scriptRows);
        $this->assertStringContainsString('dirA', $scriptRows);
        $this->assertSame(3, $model->getScriptStatusCount());

        $dataset = json_decode($model->getGraphDataSetJson(), true);
        $this->assertSame(1, $dataset['TSEP']);
        $this->assertSame([10, 90, 0], $dataset['keys']);
        $this->assertSame([2, 8, 0], $dataset['hits']);

        $this->assertStringContainsString('MB', $model->getHumanUsedMemory());
        $this->assertStringContainsString('kB', $model->getHumanFreeMemory());
        $this->assertStringContainsString('bytes', $model->getHumanWastedMemory());
        $this->assertSame(10485760, $model->getUsedMemory());
        $this->assertSame(2048, $model->getFreeMemory());
        $this->assertSame(512, $model->getWastedMemory());
        $this->assertSame('1.23', $model->getWastedMemoryPercentage());
        $this->assertNotEmpty($model->getD3Scripts());
    }

    public function testPrivateHelpersWithoutThousandSeparator()
    {
        if (! defined('THOUSAND_SEPARATOR')) {
            define('THOUSAND_SEPARATOR', false);
        }

        $this->loadOpcacheView();

        $model = new \OpCacheDataModel();
        $status = $this->buildSampleStatus();
        $config = $this->buildSampleConfig();

        $this->setPrivateProperty($model, '_status', $status);
        $this->setPrivateProperty($model, '_configuration', $config);

        $array = [];
        $method = new ReflectionMethod($model, '_arrayPset');
        $method->setAccessible(true);
        $args = [&$array, null, ['name' => 'root']];
        $method->invokeArgs($model, $args);
        $this->assertSame(['name' => 'root'], $array);

        $expectedTsep = THOUSAND_SEPARATOR === true ? 1 : 0;
        $this->assertSame($expectedTsep, json_decode($model->getGraphDataSetJson(), true)['TSEP']);

        $formatted = $this->callPrivateMethod($model, '_format_value', [1200]);
        if (THOUSAND_SEPARATOR === true) {
            $this->assertSame('1,200', $formatted);
        } else {
            $this->assertSame(1200, $formatted);
        }

        $partition = $this->callPrivateMethod($model, '_processPartition', [['size' => 1], 'leaf']);
        $this->assertSame(['size' => 1], $partition);
    }

    private function loadOpcacheView(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $root = dirname(__DIR__, 3);
        $path = $root.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'OPcache'.DIRECTORY_SEPARATOR.'Views'.DIRECTORY_SEPARATOR.'opcache.php';
        $this->assertFileExists($path);

        $cwd = getcwd();
        $sampleDir = $this->prepareSampleDataDirectory();

        $level = ob_get_level();
        ob_start();

        set_error_handler(function () {
            return true;
        });

        try {
            try {
                chdir($sampleDir);
                include $path;
            } catch (\Throwable $exception) {
                // The view executes with CLI opcache disabled; swallow rendering errors after class definition.
            }
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
            restore_error_handler();
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $loaded = true;
    }

    private function prepareSampleDataDirectory(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-opcache-view-tests';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $samplePath = $dir.DIRECTORY_SEPARATOR.'data-sample.php';
        file_put_contents($samplePath, $this->getSampleDataFileContent());

        return $dir;
    }

    private function getSampleDataFileContent(): string
    {
        return <<<'PHP'
<?php
if (!function_exists('opcache_get_configuration')) {
    function opcache_get_configuration()
    {
        return [
            'version' => [
                'version' => 'sample',
            ],
            'directives' => [
                'opcache.memory_consumption' => 134217728,
            ],
        ];
    }
}

if (!function_exists('opcache_get_status')) {
    function opcache_get_status($includeScripts = true)
    {
        $scriptPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-opcache-sample'.DIRECTORY_SEPARATOR.'sample.php';

        return [
            'memory_usage' => [
                'used_memory' => 1,
                'free_memory' => 1,
                'wasted_memory' => 0,
                'current_wasted_percentage' => 0,
            ],
            'opcache_statistics' => [
                'num_cached_keys' => 1,
                'max_cached_keys' => 2,
                'misses' => 0,
                'hits' => 1,
                'oom_restarts' => 0,
                'manual_restarts' => 0,
                'hash_restarts' => 0,
                'opcache_hit_rate' => 100,
                'blacklist_miss_ratio' => 0,
                'start_time' => 0,
                'last_restart_time' => 0,
            ],
            'scripts' => [
                $scriptPath => [
                    'hits' => 1,
                    'memory_consumption' => 128,
                ],
            ],
        ];
    }
}
PHP;
    }

    private function buildSampleConfig(): array
    {
        return [
            'version' => [
                'version' => '1.2.3',
            ],
            'directives' => [
                'opcache.enable_cli' => false,
                'opcache.memory_consumption' => 2048,
            ],
        ];
    }

    private function buildSampleStatus(): array
    {
        $baseDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-opcache-view';
        $dirA = $baseDir.DIRECTORY_SEPARATOR.'dirA';
        $dirB = $baseDir.DIRECTORY_SEPARATOR.'dirB';

        $resource = fopen('php://memory', 'r');

        $status = [
            'memory_usage' => [
                'used_memory' => 10485760,
                'free_memory' => 2048,
                'wasted_memory' => 512,
                'current_wasted_percentage' => 1.23,
                'misc' => $resource,
            ],
            'opcache_statistics' => [
                'num_cached_keys' => 10,
                'max_cached_keys' => 100,
                'misses' => 2,
                'hits' => 8,
                'oom_restarts' => 1,
                'manual_restarts' => 0,
                'hash_restarts' => 0,
                'opcache_hit_rate' => 99.5,
                'blacklist_miss_ratio' => 0.125,
                'start_time' => 1000000000,
                'last_restart_time' => 0,
            ],
            'bool_flag' => true,
            'false_flag' => false,
            'scripts' => [
                $dirA.DIRECTORY_SEPARATOR.'a.php' => [
                    'hits' => 1000,
                    'memory_consumption' => 2048,
                ],
                $dirA.DIRECTORY_SEPARATOR.'b.php' => [
                    'hits' => 2,
                    'memory_consumption' => 1048576,
                ],
                $dirB.DIRECTORY_SEPARATOR.'c.php' => [
                    'hits' => 5,
                    'memory_consumption' => 512,
                ],
            ],
        ];

        if (is_resource($resource)) {
            fclose($resource);
        }

        return $status;
    }

    private function setPrivateProperty(object $object, string $name, $value): void
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function callPrivateMethod(object $object, string $name, array $args = [])
    {
        $method = new ReflectionMethod($object, $name);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
