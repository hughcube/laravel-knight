<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Queue\Jobs\CleanFilesJob;
use Illuminate\Support\Facades\File;

class LogsPruneCommand extends Command
{
    protected $signature = 'devops:logs:prune
        {--days=14 : Delete files whose mtime is older than this many days}
        {--pattern= : Filename match (Symfony Finder name), defaults to *.log}
        {--dir= : Target directory, defaults to the storage logs path}';

    protected $description = 'Prune files under a directory (default storage/logs) older than the retention days';

    public function handle(): void
    {
        $days = intval($this->option('days'));
        $pattern = $this->option('pattern') ?: '*.log';
        $dir = $this->option('dir') ?: log_path();

        if (!File::exists($dir)) {
            $this->info(sprintf('Directory not found, skip: %s', $dir));

            return;
        }

        $result = CleanFilesJob::pruneFiles($dir, $pattern, [], $days);

        $this->info(sprintf(
            'Pruned %d files, freed %s (kept last %d days, dir %s).',
            $result['count'],
            $this->formatBytes($result['bytes']),
            $days,
            $dir
        ));
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return sprintf('%.2fG', $bytes / 1073741824);
        }
        if ($bytes >= 1048576) {
            return sprintf('%.2fM', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return sprintf('%.2fK', $bytes / 1024);
        }

        return sprintf('%dB', $bytes);
    }
}
