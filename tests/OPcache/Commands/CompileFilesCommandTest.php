<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/2/22
 * Time: 11:18.
 */

namespace HughCube\Laravel\Knight\Tests\OPcache\Commands;

use HughCube\Laravel\Knight\OPcache\Commands\CompileFilesCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CompileFilesCommandTest extends TestCase
{
    public function testRun()
    {
        $storageDir = storage_path();
        if (! is_dir($storageDir)) {
            File::makeDirectory($storageDir, 0777, true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'compile-');
        file_put_contents($tempFile, '<?php echo "ok";');

        $command = new class([$tempFile]) extends CompileFilesCommand
        {
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
    }
}
