<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Queue\Jobs\CleanFilesJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class LogsPruneCommand extends Command
{
    protected $signature = 'devops:logs:prune
        {--days=14 : Delete files whose mtime is older than this many days}
        {--pattern=* : Filename match (Symfony Finder name), defaults to *.log}
        {--dir=* : Target directory, defaults to storage and configured logs path}';

    protected $description = 'Prune files under target directories older than the retention days';

    public function handle(): void
    {
        $days = intval($this->option('days') ?: 14);

        $patterns = Collection::wrap($this->option('pattern'))->filter();
        if ($patterns->isEmpty()) {
            $patterns = Collection::make(['*.log']);
        }

        $dirs = Collection::wrap($this->option('dir'))->filter();
        if ($dirs->isEmpty()) {
            $dirs = Collection::empty()
                ->add(log_path())
                ->add(storage_path('logs'))
                ->filter()
                ->unique()
                ->values();
        }

        $existDirs = $dirs->filter(function ($dir) {
            return File::exists($dir);
        })->values();

        if ($existDirs->isEmpty()) {
            $this->info(sprintf('Directories not found, skip: %s', $dirs->implode(', ')));

            return;
        }

        $result = CleanFilesJob::pruneFiles($existDirs, $patterns, [], $days);

        $this->info(sprintf(
            'Pruned %d files, freed %s (kept last %d days, dir %s).',
            $result['count'],
            $this->formatBytes($result['bytes']),
            $days,
            $existDirs->implode(', ')
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
