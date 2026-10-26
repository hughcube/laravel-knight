<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/01/24
 * Time: 12:00.
 */

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\GenerateMixinIdeHelperCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

class GenerateMixinIdeHelperCommandTest extends TestCase
{
    /**
     * @var string
     */
    protected $outputFile;

    /**
     * @var string Unique identifier for this test run
     */
    protected $uniqueId;

    /**
     * 获取项目根目录.
     */
    protected function getProjectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->uniqueId = md5(uniqid(mt_rand(), true));
        $this->outputFile = $this->getProjectRoot().'/tests/.temp/ide_helper_test_'.$this->uniqueId.'.php';

        $outputDir = dirname($this->outputFile);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    /**
     * @return void
     */
    public function testDirectoryNotExists()
    {
        $nonExistentPath = $this->getProjectRoot().'/tests/.temp/non_existent_'.md5(uniqid());

        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $nonExistentPath,
            '--output' => $this->outputFile,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->outputFile);
    }

    /**
     * @return void
     */
    public function testNoMatchingFiles()
    {
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src',
            '--pattern' => '*NonExistentPattern*.php',
            '--output' => $this->outputFile,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->outputFile);
    }

    /**
     * @return void
     */
    public function testDryRunOption()
    {
        // src/Mixin/Support/CollectionMixin.php 有 @mixin 注解
        $path = $this->getProjectRoot().'/src/Mixin/Support';

        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $path,
            '--pattern' => 'CollectionMixin.php',
            '--output' => $this->outputFile,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->outputFile);
    }

    /**
     * @return void
     */
    public function testGenerateIdeHelperForSrcMixins()
    {
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src',
            '--pattern' => '*Mixin.php',
            '--output' => $this->outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);

        // 验证生成的内容包含已知的 Mixin 目标类 (CollectionMixin 有 @mixin Collection)
        $this->assertStringContainsString('namespace Illuminate\Support', $content);
        $this->assertStringContainsString('class Collection', $content);
    }

    /**
     * @return void
     */
    public function testGenerateWithCustomPattern()
    {
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src/Mixin/Support',
            '--pattern' => 'CollectionMixin.php',
            '--output' => $this->outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('class Collection', $content);
    }

    /**
     * @return void
     */
    public function testGenerateSpecificMixinDirectory()
    {
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src/Mixin/Support',
            '--pattern' => '*Mixin.php',
            '--output' => $this->outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        // CollectionMixin 有 @mixin Collection
        $this->assertStringContainsString('class Collection', $content);
    }

    /**
     * @return void
     */
    public function testStrMixinGeneration()
    {
        // StrMixin.php 有 @mixin Str 注解，应该成功生成
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src/Mixin/Support',
            '--pattern' => 'StrMixin.php',
            '--output' => $this->outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('namespace Illuminate\Support', $content);
        $this->assertStringContainsString('class Str', $content);
    }

    /**
     * @return void
     */
    public function testCommandInstance()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        $this->assertInstanceOf(GenerateMixinIdeHelperCommand::class, $command);
    }

    /**
     * @return void
     */
    public function testGetClassNameFromFile()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        // 使用真实的 Mixin 文件测试
        $testFile = $this->getProjectRoot().'/src/Mixin/Support/CollectionMixin.php';

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getClassNameFromFile');
        $method->setAccessible(true);

        $result = $method->invoke($command, $testFile);
        $this->assertEquals('HughCube\Laravel\Knight\Mixin\Support\CollectionMixin', $result);
    }

    /**
     * @return void
     */
    public function testMatchesPattern()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('matchesPattern');
        $method->setAccessible(true);

        // 测试 *Mixin.php 模式
        $this->assertTrue($method->invoke($command, 'CollectionMixin.php', '*Mixin.php'));
        $this->assertTrue($method->invoke($command, 'StrMixin.php', '*Mixin.php'));
        $this->assertFalse($method->invoke($command, 'Collection.php', '*Mixin.php'));
        $this->assertFalse($method->invoke($command, 'MixinHelper.php', '*Mixin.php'));

        // 测试 Collection*.php 模式
        $this->assertTrue($method->invoke($command, 'CollectionMixin.php', 'Collection*.php'));
        $this->assertTrue($method->invoke($command, 'CollectionHelper.php', 'Collection*.php'));
        $this->assertFalse($method->invoke($command, 'StrMixin.php', 'Collection*.php'));

        // 测试 ?.php 模式（单字符匹配）
        $this->assertTrue($method->invoke($command, 'A.php', '?.php'));
        $this->assertFalse($method->invoke($command, 'AB.php', '?.php'));
    }

    /**
     * @return void
     */
    public function testFormatDefaultValue()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatDefaultValue');
        $method->setAccessible(true);

        $this->assertEquals('null', $method->invoke($command, null));
        $this->assertEquals('true', $method->invoke($command, true));
        $this->assertEquals('false', $method->invoke($command, false));
        $this->assertEquals("'hello'", $method->invoke($command, 'hello'));
        $this->assertEquals('[]', $method->invoke($command, []));
        $this->assertEquals('123', $method->invoke($command, 123));
        $this->assertEquals('3.14', $method->invoke($command, 3.14));
    }

    /**
     * @return void
     */
    public function testFormatType()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatType');
        $method->setAccessible(true);

        $this->assertEquals('mixed', $method->invoke($command, null));
    }

    /**
     * @return void
     */
    public function testFindMatchingFiles()
    {
        $filesystem = new Filesystem();
        $command = new GenerateMixinIdeHelperCommand($filesystem);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findMatchingFiles');
        $method->setAccessible(true);

        $files = $method->invoke($command, $this->getProjectRoot().'/src/Mixin/Support', '*Mixin.php');

        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        // 验证找到的文件都以 Mixin.php 结尾
        foreach ($files as $file) {
            $this->assertStringEndsWith('Mixin.php', $file);
        }
    }

    /**
     * @return void
     */
    public function testDefaultPattern()
    {
        // 测试不传 pattern 参数时使用默认值
        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src/Mixin/Support',
            '--output' => $this->outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('class Collection', $content);
    }

    /**
     * 测试扫描 src 及其所有子目录的 *Mixin.php 文件并生成 IDE 帮助文件.
     *
     * @return void
     */
    public function testGenerateAllMixinsFromSrcToIdeHelper()
    {
        $outputFile = $this->getProjectRoot().'/_ide_helper.php';

        $this->artisan('ide:generate-mixin-helper', [
            '--path' => $this->getProjectRoot().'/src',
            '--pattern' => '*Mixin.php',
            '--output' => $outputFile,
        ])->assertExitCode(0);

        $this->assertFileExists($outputFile);

        $content = file_get_contents($outputFile);

        // 验证生成的内容包含多个命名空间的 Mixin
        // CollectionMixin (Illuminate\Support\Collection)
        $this->assertStringContainsString('namespace Illuminate\Support', $content);
        $this->assertStringContainsString('class Collection', $content);

        // CarbonMixin (Carbon\Carbon)
        $this->assertStringContainsString('namespace Carbon', $content);
        $this->assertStringContainsString('class Carbon', $content);

        // BuilderMixin (Illuminate\Database\Query\Builder)
        $this->assertStringContainsString('namespace Illuminate\Database\Query', $content);
        $this->assertStringContainsString('class Builder', $content);

        // 验证文件头注释
        $this->assertStringContainsString('IDE helper for App Mixin methods', $content);
        $this->assertStringContainsString('ide:generate-mixin-helper', $content);
    }
}
