<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated
 * @see CleanFilesJob
 */
class AutoCleanFileJob extends Job
{
    /**
     * @return string[][]
     */
    protected function rules(): array
    {
        return [
            'dir' => ['required'],
            'pattern' => ['nullable'],
            'max_days' => ['integer', 'min:0'],
        ];
    }

    protected function action(): void
    {
        $files = Finder::create()
            ->in($this->getDirs())
            ->name($this->getPatterns())
            ->files();

        $count = 0;
        foreach ($files as $file) {
            $mDate = Carbon::createFromTimestamp($file->getMTime());
            if (! $mDate->addDays($this->getMaxDays())->isPast()) {
                continue;
            }

            $count++;
            File::delete($file);
        }

        $this->info(sprintf(
            'Delete %s "%s" files in the "%s".',
            $count,
            implode(',', $this->getPatterns()),
            implode(',', $this->getDirs())
        ));
    }

    protected function getMaxDays(): int
    {
        return intval($this->p()->get('max_days') ?: 30);
    }

    /**
     * @return array
     */
    protected function getDirs(): array
    {
        $dir = $this->p()->get('dir');

        return is_array($dir) ? $dir : [$dir];
    }

    /**
     * @return array
     */
    protected function getPatterns(): array
    {
        $pattern = $this->p()->get('pattern') ?: '*';

        return is_array($pattern) ? $pattern : [$pattern];
    }
}
