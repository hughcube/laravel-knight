<?php

namespace HughCube\Laravel\Knight\Tests\OPcache\Views;

use HughCube\Laravel\Knight\Tests\TestCase;

class OpcacheViewTest extends TestCase
{
    public function testViewRendersWhenOpcacheEnabled()
    {
        if (extension_loaded('Zend OPcache') && @opcache_get_status() === false) {
            $this->markTestSkipped('OPcache extension loaded but not active; cannot mock built-in functions.');
        }

        $path = realpath(dirname(__DIR__, 3).'/src/OPcache/Views/opcache.php');
        if ($path === false) {
            $this->markTestSkipped('OPcache view file not found.');
        }

        $this->prepareSampleDataDirectory();
        $cwd = getcwd();

        ob_start();

        try {
            chdir(sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-opcache-view-tests');
            include $path;
        } finally {
            $output = ob_get_clean();
            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('OpCache', $output);
    }

    private function prepareSampleDataDirectory(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-opcache-view-tests';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $samplePath = $dir.DIRECTORY_SEPARATOR.'data-sample.php';
        file_put_contents(
            $samplePath,
            <<<'PHP'
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
PHP
        );
    }
}
