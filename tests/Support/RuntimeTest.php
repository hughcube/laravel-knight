<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use HughCube\Laravel\Knight\Support\Runtime;
use HughCube\Laravel\Knight\Tests\TestCase;

class RuntimeTest extends TestCase
{
    /**
     * @var mixed
     */
    private $originalArgv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalArgv = $_SERVER['argv'] ?? null;
    }

    protected function tearDown(): void
    {
        if (null === $this->originalArgv) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }
        parent::tearDown();
    }

    /**
     * 正常执行: php artisan wal:event-dispatch --batch=200.
     */
    public function testGetCommandNormal()
    {
        $_SERVER['argv'] = ['artisan', 'wal:event-dispatch', '--batch=200'];
        $this->assertSame('wal:event-dispatch', Runtime::getCommand());
    }

    /**
     * 带完整路径: php /var/www/html/artisan queue:work.
     */
    public function testGetCommandWithFullPath()
    {
        $_SERVER['argv'] = ['/var/www/html/artisan', 'queue:work', '--tries=3'];
        $this->assertSame('queue:work', Runtime::getCommand());
    }

    /**
     * stdin 执行场景: argv[0] = "Standard input code".
     */
    public function testGetCommandFromStdin()
    {
        $_SERVER['argv'] = ['Standard input code', 'artisan', 'wal:event-dispatch', '--batch=200'];
        $this->assertSame('wal:event-dispatch', Runtime::getCommand());
    }

    /**
     * stdin 执行场景, artisan 带完整路径.
     */
    public function testGetCommandFromStdinWithFullPath()
    {
        $_SERVER['argv'] = ['Standard input code', '/var/www/html/artisan', 'schedule:run'];
        $this->assertSame('schedule:run', Runtime::getCommand());
    }

    /**
     * 只有 artisan，没有命令参数.
     */
    public function testGetCommandWithNoCommand()
    {
        $_SERVER['argv'] = ['artisan'];
        $this->assertNull(Runtime::getCommand());
    }

    /**
     * argv 为空.
     */
    public function testGetCommandWithEmptyArgv()
    {
        $_SERVER['argv'] = [];
        $this->assertNull(Runtime::getCommand());
    }

    /**
     * isRunningCommand 通配符匹配.
     */
    public function testIsRunningCommandWithWildcard()
    {
        $_SERVER['argv'] = ['artisan', 'wal:event-dispatch', '--batch=200'];
        $this->assertTrue(Runtime::isRunningCommand('wal:*'));
        $this->assertFalse(Runtime::isRunningCommand('queue:*'));
    }

    /**
     * isRunningCommand 在 stdin 场景下正确匹配.
     */
    public function testIsRunningCommandFromStdin()
    {
        $_SERVER['argv'] = ['Standard input code', 'artisan', 'wal:event-dispatch', '--batch=200'];
        $this->assertTrue(Runtime::isRunningCommand('wal:*'));
        $this->assertTrue(Runtime::isRunningCommand('wal:event-dispatch'));
        $this->assertFalse(Runtime::isRunningCommand('artisan'));
    }

    /**
     * isQueueWorker 在 stdin 场景下正确识别.
     */
    public function testIsQueueWorkerFromStdin()
    {
        $_SERVER['argv'] = ['Standard input code', 'artisan', 'queue:work', '--tries=3'];
        $this->assertTrue(Runtime::isQueueWorker());
    }

    /**
     * 全局选项出现在命令名之前: php artisan --env=testing -v migrate.
     */
    public function testGetCommandSkipsOptionsBeforeCommand()
    {
        $_SERVER['argv'] = ['artisan', '--env=testing', '-v', 'migrate'];
        $this->assertSame('migrate', Runtime::getCommand());
    }

    /**
     * stdin + 全局选项在前.
     */
    public function testGetCommandSkipsOptionsFromStdin()
    {
        $_SERVER['argv'] = ['Standard input code', 'artisan', '-vvv', '--env=production', 'wal:event-dispatch'];
        $this->assertSame('wal:event-dispatch', Runtime::getCommand());
    }

    /**
     * 只有选项没有命令: php artisan --version.
     */
    public function testGetCommandOnlyOptions()
    {
        $_SERVER['argv'] = ['artisan', '--version'];
        $this->assertNull(Runtime::getCommand());
    }

    /**
     * 无 artisan 的 CLI 场景 (如 phpunit).
     */
    public function testGetCommandWithoutArtisan()
    {
        $_SERVER['argv'] = ['vendor/bin/phpunit', 'tests/Support/RuntimeTest.php'];
        $this->assertNull(Runtime::getCommand());
    }

    /**
     * isRunningCommand 在有前置选项时正确匹配.
     */
    public function testIsRunningCommandWithLeadingOptions()
    {
        $_SERVER['argv'] = ['artisan', '--env=testing', 'queue:work', '--tries=3'];
        $this->assertTrue(Runtime::isRunningCommand('queue:*'));
        $this->assertTrue(Runtime::isQueueWorker());
    }
}
