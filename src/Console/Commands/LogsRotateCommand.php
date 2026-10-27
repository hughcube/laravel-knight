<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Queue\Jobs\RotateFileJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

class LogsRotateCommand extends Command
{
    protected $signature = 'devops:logs:rotate
        {--pattern=* : Filename match (Symfony Finder name), defaults to *.log}
        {--dir=* : Target directory, defaults to storage and configured logs path}
        {--date-format= : Date format suffix for rotated filename}
        {--keep-empty : Do not skip empty files (for full rotation)}
        {--max-size= : Trigger rotate only when file size exceeds this (e.g. 10M). If omitted, full file rotation is performed}
        {--keep-size= : Size to retain in live file after trim (e.g. 5M, defaults to half of max-size)}
        {--keep-versions=30 : Number of archive files to retain (default: 30)}
        {--archive-dir= : Custom directory for archives}';

    protected $description = 'Rotate or trim log files under target directories';

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
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

        $dateFormat = $this->option('date-format') ?: null;
        $skipEmpty = ! $this->option('keep-empty');
        $maxSize = $this->option('max-size');
        $keepSize = $this->option('keep-size');
        $keepVersions = intval($this->option('keep-versions') ?: 30);
        $archiveDir = $this->option('archive-dir');

        $result = RotateFileJob::rotateFiles(
            $existDirs,
            $patterns,
            [],
            $dateFormat,
            $skipEmpty,
            $maxSize,
            $keepSize,
            $keepVersions,
            $archiveDir
        );

        if ('trim' === ($result['mode'] ?? 'rotate')) {
            $this->info(sprintf(
                'Trim finished in dir %s: found %d files, trimmed %d, unchanged %d, warn %d, error %d.',
                $existDirs->implode(', '),
                $result['found'],
                $result['trimmed'],
                $result['unchanged'],
                $result['warn'],
                $result['error']
            ));

            foreach ($result['items'] as $item) {
                if ('trimmed' === $item['status'] || 'warn' === $item['status'] || 'error' === $item['status']) {
                    $this->line(sprintf(
                        '[%s] %s %s -> %s | %s',
                        strtoupper($item['status']),
                        $item['file'],
                        RotateFileJob::formatBytes($item['before']),
                        RotateFileJob::formatBytes($item['after']),
                        $item['message']
                    ));
                }
            }
        } else {
            $this->info(sprintf(
                'Rotated %d files (total found %d, skipped %d empty files, dir %s).',
                $result['rotated'],
                $result['found'],
                $result['skipped'],
                $existDirs->implode(', ')
            ));
        }
    }
}
