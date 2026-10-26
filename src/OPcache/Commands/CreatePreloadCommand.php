<?php

namespace HughCube\Laravel\Knight\OPcache\Commands;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CreatePreloadCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'opcache:create-preload
                            {--output= : Output file path (default: base_path/preload.php) }
                            {--with-remote-scripts : Include remote cached scripts in preload file }
                            {--skip-bootstrap : Skip Laravel application bootstrap }';

    /**
     * @inheritdoc
     */
    protected $description = 'Create PHP preload file for OPcache optimization';

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws Exception
     */
    public function handle()
    {
        $outputPath = $this->option('output') ?: base_path('preload.php');

        $this->info('Creating OPcache preload file...');
        $this->info(sprintf('Output: %s', $outputPath));

        $process = new Process(
            $this->serverCommand(),
            base_path(),
            [
                'WITH_REMOTE_SCRIPTS' => strval(intval($this->option('with-remote-scripts'))),
                'SKIP_BOOTSTRAP' => strval(intval($this->option('skip-bootstrap'))),
                'OUTPUT_PATH' => $outputPath,
            ]
        );

        try {
            $process->mustRun(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->error(trim($buffer));
                } else {
                    $this->line(trim($buffer));
                }
            });

            $this->info('Preload file created successfully!');
        } catch (Exception $e) {
            $this->error('Failed to create preload file: '.$e->getMessage());

            throw $e;
        }
    }

    /**
     * Get the full server command.
     *
     * @return array
     */
    protected function serverCommand(): array
    {
        $server = file_exists(base_path('create_preload.php'))
            ? base_path('create_preload.php')
            : __DIR__.'/../../../resources/create_preload.php';

        return [
            (new PhpExecutableFinder())->find(false),
            $server,
        ];
    }
}
