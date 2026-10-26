<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use Exception;
use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;

class RotateFileJob extends Job
{
    /**
     * @return array
     */
    protected function rules(): array
    {
        return [
            'items' => ['array'],
            'items.*.dir' => ['required'],
            'items.*.pattern' => ['nullable'],
            'items.*.exclude' => ['nullable'],
            'items.*.date_format' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws Throwable
     */
    protected function action(): void
    {
        foreach ($this->p('items', []) as $item) {
            $this->rotateFiles($item);
        }
    }

    /**
     * @throws Throwable
     */
    protected function rotateFiles($job)
    {
        $skipEmpty = $job['skip_empty'] ?? true;
        $dateFormat = $job['date_format'] ?: 'Y-m-d';
        $dirs = Collection::wrap($job['dir'])->filter();
        $patterns = Collection::wrap($job['pattern'] ?? '*')->filter();
        $excludes = Collection::wrap($job['exclude'] ?? null ?: [])->filter()->values();

        /** @var Collection $existDirs */
        $existDirs = $dirs->filter(function ($dir) {
            return File::exists($dir);
        })->values();

        $files = [];
        if ($existDirs->isNotEmpty()) {
            $files = Finder::create()
                ->in($existDirs->all())
                ->name($patterns->all())
                ->notName($excludes->all())
                ->ignoreUnreadableDirs()
                ->files();
        }

        $rotates = Collection::empty();
        foreach ($files as $file) {
            $datePath = sprintf(
                '%s/%s-%s%s',
                File::dirname($file->getRealPath()),
                $file->getFilenameWithoutExtension(),
                Carbon::now()->format($dateFormat),
                $file->getExtension() ? sprintf('.%s', $file->getExtension()) : ''
            );

            if (0 < $file->getSize() || ! $skipEmpty) {
                $this->rotate($file->getRealPath(), $datePath);
                $rotates = $rotates->add(['path' => $file->getRealPath(), 'date_path' => $datePath]);
            }
        }

        $this->info($message = sprintf(
            'In directory "%s" that match "%s" find %s files%s, skip %s empty files, rotated %s files%s.',
            $dirs->implode(', '),
            $patterns->implode(', '),
            $files->count(),
            0 >= $files->count() ? '' : sprintf(' (%s)', Collection::make($files->getIterator())->map(function (SplFileInfo $file) use ($dirs) {
                return $dirs->count() > 1 ? $file->getRealPath() : $file->getBasename();
            })->implode(', ')),
            $files->count() - $rotates->count(),
            $rotates->count(),
            0 >= $rotates->count() ? '' : sprintf(' (%s)', $rotates->map(function ($result) use ($dirs) {
                if ($dirs->count() > 1) {
                    return sprintf('[%s => %s]', $result['path'], $result['date_path']);
                } else {
                    return sprintf('[%s => %s]', File::basename($result['path']), File::basename($result['date_path']));
                }
            })->implode(', '))
        ));
    }

    /**
     * @throws Exception
     */
    protected function rotate($from, $to)
    {
        /** 打开文件 */
        $handle = $dateHandle = null;

        try {
            $handle = fopen($from, 'r+b');
            if (! is_resource($handle)) {
                throw new Exception('Failed to open file!');
            }

            $dateHandle = fopen($to, 'ab');
            if (! is_resource($dateHandle)) {
                throw new Exception('Failed to open date file!');
            }
        } catch (Throwable $exception) {
            is_resource($handle) and fclose($handle);
            is_resource($dateHandle) and fclose($dateHandle);

            throw $exception;
        }

        /** 复制文件并且截断文件 */
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new Exception('Failed to lock the file!');
            }

            stream_copy_to_stream($handle, $dateHandle);

            ftruncate($handle, 0);

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
            fclose($dateHandle);
        }
    }
}
