<?php

namespace HughCube\Laravel\Knight\Tests\OPcache\Commands;

use Exception;
use HughCube\Laravel\Knight\OPcache\Commands\CreatePreloadCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CreatePreloadCommandTest extends TestCase
{
    public function testServerCommandUsesCreatePreloadScript()
    {
        $command = new CreatePreloadCommand();

        $serverCommand = $this->callMethod($command, 'serverCommand');

        $this->assertIsArray($serverCommand);
        $this->assertCount(2, $serverCommand);
        $this->assertNotEmpty($serverCommand[0]);
        $this->assertTrue(str_ends_with($serverCommand[1], 'create_preload.php'));
    }

    public function testServerCommandPrefersLocalCreatePreloadFile()
    {
        $path = base_path('create_preload.php');
        $existed = file_exists($path);

        if (! $existed) {
            file_put_contents($path, '<?php echo "ok";');
        }

        try {
            $command = new CreatePreloadCommand();
            $serverCommand = $this->callMethod($command, 'serverCommand');

            $this->assertSame($path, $serverCommand[1]);
        } finally {
            if (! $existed && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testHandleCreatesPreloadFile()
    {
        $script = tempnam(sys_get_temp_dir(), 'preload-script-').'.php';
        $output = tempnam(sys_get_temp_dir(), 'preload-out-');

        file_put_contents($script, <<<'PHP'
<?php
$output = getenv('OUTPUT_PATH');
if ($output) {
    file_put_contents($output, 'ok');
}
echo "done";
PHP);

        $command = new class($script, $output) extends CreatePreloadCommand
        {
            private string $script;
            private string $outputPath;

            public function __construct(string $script, string $output)
            {
                parent::__construct();
                $this->script = $script;
                $this->outputPath = $output;
            }

            protected function serverCommand(): array
            {
                return [PHP_BINARY, $this->script];
            }

            public function option($key = null)
            {
                if ($key === 'output') {
                    return $this->outputPath;
                }

                return 0;
            }
        };

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        try {
            $command->handle();

            $this->assertFileExists($output);
            $this->assertSame('ok', file_get_contents($output));
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
            if (is_file($output)) {
                unlink($output);
            }
        }
    }

    public function testHandleThrowsWhenProcessFails()
    {
        $script = tempnam(sys_get_temp_dir(), 'preload-script-').'.php';
        $output = tempnam(sys_get_temp_dir(), 'preload-out-');
        if (is_file($output)) {
            unlink($output);
        }

        file_put_contents($script, <<<'PHP'
<?php
fwrite(STDERR, 'boom');
exit(1);
PHP);

        $command = new class($script, $output) extends CreatePreloadCommand
        {
            private string $script;
            private string $outputPath;

            public function __construct(string $script, string $output)
            {
                parent::__construct();
                $this->script = $script;
                $this->outputPath = $output;
            }

            protected function serverCommand(): array
            {
                return [PHP_BINARY, $this->script];
            }

            public function option($key = null)
            {
                if ($key === 'output') {
                    return $this->outputPath;
                }

                return 0;
            }
        };

        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        try {
            $command->handle();
            $this->fail('Expected Exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('failed', strtolower($exception->getMessage()));
            $this->assertFalse(is_file($output));
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
            if (is_file($output)) {
                unlink($output);
            }
        }
    }
}
