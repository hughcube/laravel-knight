<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CleanFilesJob extends Job
{
    /**
     * @return array
     */
    protected function rules(): array
    {
        return [
            'items'            => ['array'],
            'items.*.dir'      => ['required'],
            'items.*.pattern'  => ['nullable'],
            'items.*.exclude'  => ['nullable'],
            'items.*.max_days' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function action(): void
    {
        foreach ($this->p('items', []) as $item) {
            $this->cleanFiles($item);
        }
    }

    protected function cleanFiles($job)
    {
        $dirs = Collection::wrap($job['dir'])->filter();
        $patterns = Collection::wrap($job['pattern'] ?? '*')->filter()->values();

        $result = static::pruneFiles(
            $job['dir'],
            $job['pattern'] ?? '*',
            $job['exclude'] ?? null ?: [],
            intval($job['max_days'] ?: 30)
        );

        $this->info(sprintf(
            'Delete %s files in directory "%s" that match "%s".',
            $result['count'],
            $dirs->implode(', '),
            $patterns->implode(', ')
        ));
    }

    /**
     * 删除 dirs 下匹配 patterns 且 mtime 早于 maxDays 的文件, 返回 ['count' => int, 'bytes' => int].
     *
     * @param string|array|Collection $dirs
     * @param string|array|Collection $patterns
     * @param string|array|Collection $excludes
     */
    public static function pruneFiles($dirs, $patterns = '*', $excludes = [], int $maxDays = 30): array
    {
        $dirs = Collection::wrap($dirs)->filter();
        $patterns = Collection::wrap($patterns ?: '*')->filter()->values();
        $excludes = Collection::wrap($excludes ?: [])->filter()->values();

        /** @var Collection $existDirs */
        $existDirs = $dirs->filter(function ($dir) {
            return File::exists($dir);
        })->values();

        $count = 0;
        $bytes = 0;

        if ($existDirs->isEmpty()) {
            return ['count' => $count, 'bytes' => $bytes];
        }

        $files = Finder::create()
            ->in($existDirs->all())
            ->name($patterns->all())
            ->notName($excludes->all())
            ->ignoreUnreadableDirs()
            ->files();

        foreach ($files as $file) {
            $mDate = Carbon::createFromTimestamp($file->getMTime());
            if (!$mDate->addDays($maxDays)->isPast()) {
                continue;
            }

            $bytes += $file->getSize();
            $count++;
            File::delete($file->getRealPath());
        }

        return ['count' => $count, 'bytes' => $bytes];
    }
}
