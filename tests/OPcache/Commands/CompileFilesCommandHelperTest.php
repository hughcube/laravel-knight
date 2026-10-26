<?php

namespace HughCube\Laravel\Knight\Tests\OPcache\Commands;

use HughCube\Laravel\Knight\OPcache\Commands\CompileFilesCommand;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CompileFilesCommandHelperTest extends TestCase
{
    public function testFindPhpScriptsIncludesShebangFiles()
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight_opcache_scripts_'.uniqid();
        mkdir($dir, 0777, true);

        $phpFile = $dir.DIRECTORY_SEPARATOR.'a.php';
        $shebangFile = $dir.DIRECTORY_SEPARATOR.'b';
        $textFile = $dir.DIRECTORY_SEPARATOR.'c.txt';

        file_put_contents($phpFile, "<?php echo 'a';");
        file_put_contents($shebangFile, "#!/usr/bin/env php\n<?php echo 'b';");
        file_put_contents($textFile, 'nope');

        try {
            $command = new CompileFilesCommand();
            $scripts = $this->callMethod($command, 'findPhpScripts', [$dir, false]);

            $this->assertContains(realpath($phpFile), $scripts);
            $this->assertContains(realpath($shebangFile), $scripts);
            $this->assertNotContains(realpath($textFile), $scripts);
        } finally {
            @unlink($phpFile);
            @unlink($shebangFile);
            @unlink($textFile);
            @rmdir($dir);
        }
    }

    public function testFindPhpScriptsReturnsEmptyForMissingDirectory()
    {
        $command = new CompileFilesCommand();
        $missingDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight_missing_'.uniqid();

        $scripts = $this->callMethod($command, 'findPhpScripts', [$missingDir, false]);

        $this->assertSame([], $scripts);
    }

    public function testFindPhpScriptsFollowsLinksFlag()
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight_opcache_follow_'.uniqid();
        mkdir($dir, 0777, true);

        $phpFile = $dir.DIRECTORY_SEPARATOR.'a.php';
        file_put_contents($phpFile, "<?php echo 'a';");

        try {
            $command = new CompileFilesCommand();
            $scripts = $this->callMethod($command, 'findPhpScripts', [$dir, true]);

            $this->assertContains(realpath($phpFile), $scripts);
        } finally {
            @unlink($phpFile);
            @rmdir($dir);
        }
    }

    public function testCompileProcessCodeIncludesOpcacheCompile()
    {
        $command = $this->makeCommand();

        $code = $this->callMethod($command, 'compileProcessCode', ['C:\\tmp\\scripts.json']);

        $this->assertStringContainsString('opcache_compile_file', $code);
        $this->assertStringContainsString('scripts.json', $code);
    }

    public function testGetAppFilesReturnsEmptyWhenDisabled()
    {
        $command = $this->makeCommand(['with-app-files' => 0]);

        $files = $this->callMethod($command, 'getAppFiles');

        $this->assertSame([], $files);
    }

    public function testGetAppFilesIncludesConfig()
    {
        $command = $this->makeCommand(['with-app-files' => 1]);

        $configDir = base_path('config');
        $createdDir = false;

        if (! is_dir($configDir)) {
            mkdir($configDir, 0777, true);
            $createdDir = true;
        }

        $tempFile = $configDir.DIRECTORY_SEPARATOR.'knight_test.php';
        file_put_contents($tempFile, '<?php return [];');

        try {
            $files = array_map('realpath', $this->callMethod($command, 'getAppFiles'));

            $this->assertContains(realpath($tempFile), $files);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
            if ($createdDir && is_dir($configDir)) {
                rmdir($configDir);
            }
        }
    }

    public function testGetAppFilesReturnsEmptyWhenDirectoriesMissing()
    {
        $this->withTempBasePath(function ($basePath) {
            $command = $this->makeCommand(['with-app-files' => 1]);

            $files = $this->callMethod($command, 'getAppFiles');

            $this->assertSame([], $files);
        });
    }

    public function testGetComposerFilesReturnsEmptyWhenDisabled()
    {
        $command = $this->makeCommand(['with-composer-files' => 0]);

        $files = $this->callMethod($command, 'getComposerFiles');

        $this->assertSame([], $files);
    }

    public function testGetComposerFilesLoadsAutoloadSources()
    {
        $this->withTempBasePath(function ($basePath) {
            $fixtures = $this->createComposerAutoloadFixtures($basePath, false);

            $command = $this->makeCommand(['with-composer-files' => 1]);
            $files = array_map('realpath', $this->callMethod($command, 'getComposerFiles'));

            $this->assertContains(realpath($fixtures['classmap']), $files);
            $this->assertContains(realpath($fixtures['autoload']), $files);
            $this->assertContains(realpath($fixtures['psr4']), $files);
        });
    }

    public function testGetComposerFilesFiltersVendorWhenOptionSet()
    {
        $this->withTempBasePath(function ($basePath) {
            $fixtures = $this->createComposerAutoloadFixtures($basePath, true);

            $command = $this->makeCommand([
                'with-composer-files' => 1,
                'without-vendor' => 1,
            ]);

            $files = array_map('realpath', $this->callMethod($command, 'getComposerFiles'));

            $this->assertNotContains(realpath($fixtures['vendor']), $files);
            $this->assertContains(realpath($fixtures['classmap']), $files);
        });
    }

    public function testGetVendorBinFilesAndOctaneFilesUseFinder()
    {
        $this->withTempBasePath(function ($basePath) {
            $vendorBin = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin';
            $octaneDir = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'laravel'.DIRECTORY_SEPARATOR.'octane';
            mkdir($vendorBin, 0777, true);
            mkdir($octaneDir, 0777, true);

            $vendorFile = $vendorBin.DIRECTORY_SEPARATOR.'script.php';
            $octaneFile = $octaneDir.DIRECTORY_SEPARATOR.'octane.php';
            file_put_contents($vendorFile, '<?php echo "bin";');
            file_put_contents($octaneFile, '<?php echo "octane";');

            $command = $this->makeCommand();
            $vendorFiles = $this->callMethod($command, 'getVendorBinFiles');
            $octaneFiles = $this->callMethod($command, 'getOctaneFiles');

            $this->assertContains(realpath($vendorFile), $vendorFiles);
            $this->assertContains(realpath($octaneFile), $octaneFiles);
        });
    }

    public function testGetProdFilesReturnsEmptyWhenDisabled()
    {
        $command = $this->makeCommand(['with-remote-scripts' => 0]);

        $files = $this->callMethod($command, 'getProdFiles');

        $this->assertSame([], $files);
    }

    public function testGetProdFilesMapsRemoteScripts()
    {
        $property = new ReflectionProperty(OPcache::class, 'instance');
        $property->setAccessible(true);
        $previous = $property->getValue();

        $stub = new class() extends OPcache
        {
            public function getRemoteScripts($url = null, $timeout = 5, $useAppHost = true): array
            {
                return ['foo.php', 'bar/baz.php'];
            }
        };

        $property->setValue(null, $stub);

        try {
            $command = $this->makeCommand(['with-remote-scripts' => 1]);

            $files = $this->callMethod($command, 'getProdFiles');

            $this->assertContains(base_path('foo.php'), $files);
            $this->assertContains(base_path('bar/baz.php'), $files);
        } finally {
            $property->setValue(null, $previous);
        }
    }

    public function testGetProdFilesWarnsOnException()
    {
        $property = new ReflectionProperty(OPcache::class, 'instance');
        $property->setAccessible(true);
        $previous = $property->getValue();

        $stub = new class() extends OPcache
        {
            public function getRemoteScripts($url = null, $timeout = 5, $useAppHost = true): array
            {
                throw new \RuntimeException('remote failure');
            }
        };

        $property->setValue(null, $stub);

        $command = new class(['with-remote-scripts' => 1]) extends CompileFilesCommand
        {
            public array $warnings = [];
            private array $options;

            public function __construct(array $options)
            {
                parent::__construct();
                $this->options = $options;
            }

            public function option($key = null)
            {
                return $this->options[$key] ?? null;
            }

            public function warn($string, $verbosity = null)
            {
                $this->warnings[] = $string;
            }
        };

        try {
            $files = $this->callMethod($command, 'getProdFiles');

            $this->assertSame([], $files);
            $this->assertTrue(collect($command->warnings)->contains(function ($message) {
                return is_string($message) && str_contains($message, 'remote failure');
            }));
        } finally {
            $property->setValue(null, $previous);
        }
    }

    public function testHandleCompilesFilesWithStubbedProcess()
    {
        $storageDir = storage_path();
        if (! is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'compile-');
        file_put_contents($tempFile, '<?php echo "ok";');

        $command = new class([$tempFile]) extends CompileFilesCommand
        {
            public array $messages = [];
            private array $files;

            public function __construct(array $files)
            {
                parent::__construct();
                $this->files = $files;
            }

            protected function compileProcessCode($file): string
            {
                return sprintf(
                    '$dataFile=%s;$classmap=json_decode(file_get_contents($dataFile), true);file_put_contents($dataFile, json_encode([]));',
                    var_export($file, true)
                );
            }

            protected function getAppFiles(): array
            {
                return $this->files;
            }

            protected function getComposerFiles(): array
            {
                return [];
            }

            protected function getProdFiles(): array
            {
                return [];
            }

            protected function getVendorBinFiles(): array
            {
                return [];
            }

            protected function getOctaneFiles(): array
            {
                return [];
            }

            public function info($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }

            public function warn($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }
        };

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        try {
            $command->handle(new Schedule($this->app));
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->assertFalse(is_file(storage_path('opcache_compile_files.json')));
        $this->assertTrue(collect($command->messages)->contains(function ($message) {
            return is_string($message) && str_contains($message, 'Successfully compiled');
        }));
    }

    public function testHandleReportsRemoteVendorAndOctaneFiles()
    {
        $storageDir = storage_path();
        if (! is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $appFile = tempnam(sys_get_temp_dir(), 'compile-app-');
        $remoteFile = tempnam(sys_get_temp_dir(), 'compile-remote-');
        $vendorFile = tempnam(sys_get_temp_dir(), 'compile-vendor-');
        $octaneFile = tempnam(sys_get_temp_dir(), 'compile-octane-');

        file_put_contents($appFile, '<?php echo "app";');
        file_put_contents($remoteFile, '<?php echo "remote";');
        file_put_contents($vendorFile, '<?php echo "vendor";');
        file_put_contents($octaneFile, '<?php echo "octane";');

        $command = new class([$appFile], [$remoteFile], [$vendorFile], [$octaneFile]) extends CompileFilesCommand
        {
            public array $messages = [];
            private array $appFiles;
            private array $remoteFiles;
            private array $vendorFiles;
            private array $octaneFiles;

            public function __construct(array $appFiles, array $remoteFiles, array $vendorFiles, array $octaneFiles)
            {
                parent::__construct();
                $this->appFiles = $appFiles;
                $this->remoteFiles = $remoteFiles;
                $this->vendorFiles = $vendorFiles;
                $this->octaneFiles = $octaneFiles;
            }

            protected function compileProcessCode($file): string
            {
                return sprintf(
                    '$dataFile=%s;$classmap=json_decode(file_get_contents($dataFile), true);file_put_contents($dataFile, json_encode([]));',
                    var_export($file, true)
                );
            }

            protected function getAppFiles(): array
            {
                return $this->appFiles;
            }

            protected function getComposerFiles(): array
            {
                return [];
            }

            protected function getProdFiles(): array
            {
                return $this->remoteFiles;
            }

            protected function getVendorBinFiles(): array
            {
                return $this->vendorFiles;
            }

            protected function getOctaneFiles(): array
            {
                return $this->octaneFiles;
            }

            public function info($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }

            public function warn($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }
        };

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        try {
            $command->handle(new Schedule($this->app));
        } finally {
            foreach ([$appFile, $remoteFile, $vendorFile, $octaneFile] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        $this->assertTrue(collect($command->messages)->contains(function ($message) {
            return is_string($message) && str_contains($message, 'Remote cached files');
        }));
        $this->assertTrue(collect($command->messages)->contains(function ($message) {
            return is_string($message) && str_contains($message, 'Vendor bin files');
        }));
        $this->assertTrue(collect($command->messages)->contains(function ($message) {
            return is_string($message) && str_contains($message, 'Octane files');
        }));
    }

    public function testHandleWarnsWhenNoFiles()
    {
        $command = new class() extends CompileFilesCommand
        {
            public array $messages = [];

            protected function getAppFiles(): array
            {
                return [];
            }

            protected function getComposerFiles(): array
            {
                return [];
            }

            protected function getProdFiles(): array
            {
                return [];
            }

            protected function getVendorBinFiles(): array
            {
                return [];
            }

            protected function getOctaneFiles(): array
            {
                return [];
            }

            public function info($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }

            public function warn($string, $verbosity = null)
            {
                $this->messages[] = $string;
            }
        };

        $command->setLaravel($this->app);
        $command->handle(new Schedule($this->app));

        $this->assertTrue(collect($command->messages)->contains(function ($message) {
            return is_string($message) && str_contains($message, 'No files to compile');
        }));
    }

    private function createComposerAutoloadFixtures(string $basePath, bool $includeVendorFile): array
    {
        $composerDir = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer';
        $srcDir = $basePath.DIRECTORY_SEPARATOR.'src';
        $psr4Dir = $srcDir.DIRECTORY_SEPARATOR.'Psr4';

        mkdir($composerDir, 0777, true);
        mkdir($psr4Dir, 0777, true);

        $classmapFile = $srcDir.DIRECTORY_SEPARATOR.'Classmap.php';
        $autoloadFile = $srcDir.DIRECTORY_SEPARATOR.'helpers.php';
        $psr4File = $psr4Dir.DIRECTORY_SEPARATOR.'Psr4Class.php';
        $vendorFile = base_path('vendor/').'vendor_file.php';

        file_put_contents($classmapFile, '<?php echo "classmap";');
        file_put_contents($autoloadFile, '<?php echo "autoload";');
        file_put_contents($psr4File, '<?php echo "psr4";');

        if ($includeVendorFile) {
            file_put_contents($vendorFile, '<?php echo "vendor";');
        }

        $classmap = ['Foo' => $classmapFile];
        if ($includeVendorFile) {
            $classmap['VendorFoo'] = $vendorFile;
        }

        file_put_contents(
            $composerDir.DIRECTORY_SEPARATOR.'autoload_classmap.php',
            '<?php return '.var_export($classmap, true).';'
        );
        file_put_contents(
            $composerDir.DIRECTORY_SEPARATOR.'autoload_files.php',
            '<?php return '.var_export([$autoloadFile], true).';'
        );
        file_put_contents(
            $composerDir.DIRECTORY_SEPARATOR.'autoload_psr4.php',
            '<?php return '.var_export(['Test\\' => [$psr4Dir]], true).';'
        );

        return [
            'classmap' => $classmapFile,
            'autoload' => $autoloadFile,
            'psr4' => $psr4File,
            'vendor' => $vendorFile,
        ];
    }

    private function withTempBasePath(callable $callback): void
    {
        $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight_base_'.uniqid();
        mkdir($basePath, 0777, true);

        $originalBasePath = $this->app->basePath();
        $this->app->setBasePath($basePath);

        try {
            $callback($basePath);
        } finally {
            $this->app->setBasePath($originalBasePath);
            $this->removeDirectory($basePath);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    public function testGetProcessTimeoutDefaultIsNull()
    {
        $command = $this->makeCommand([]);
        $this->assertNull($this->callMethod($command, 'getProcessTimeout'));

        $commandEmpty = $this->makeCommand(['timeout' => '']);
        $this->assertNull($this->callMethod($commandEmpty, 'getProcessTimeout'));

        $commandZero = $this->makeCommand(['timeout' => '0']);
        $this->assertNull($this->callMethod($commandZero, 'getProcessTimeout'));

        $commandNegative = $this->makeCommand(['timeout' => '-10']);
        $this->assertNull($this->callMethod($commandNegative, 'getProcessTimeout'));
    }

    public function testGetProcessTimeoutWithValidValue()
    {
        $command = $this->makeCommand(['timeout' => '300']);
        $this->assertSame(300.0, $this->callMethod($command, 'getProcessTimeout'));

        $commandFloat = $this->makeCommand(['timeout' => '12.5']);
        $this->assertSame(12.5, $this->callMethod($commandFloat, 'getProcessTimeout'));
    }

    private function makeCommand(array $options = []): CompileFilesCommand
    {
        return new class($options) extends CompileFilesCommand
        {
            private array $options;

            public function __construct(array $options)
            {
                parent::__construct();
                $this->options = $options;
            }

            public function option($key = null)
            {
                return $this->options[$key] ?? null;
            }
        };
    }
}
