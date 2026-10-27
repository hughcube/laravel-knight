<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use Exception;
use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            'items.*.skip_empty' => ['nullable', 'boolean'],
            'items.*.max_size' => ['nullable'],
            'items.*.keep_size' => ['nullable'],
            'items.*.keep_versions' => ['nullable', 'integer', 'min:1'],
            'items.*.archive_dir' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws Throwable
     */
    protected function action(): void
    {
        foreach ($this->p('items', []) as $item) {
            $this->rotateItem($item);
        }
    }

    /**
     * @throws Throwable
     */
    protected function rotateItem($job)
    {
        $skipEmpty = $job['skip_empty'] ?? true;
        $dateFormat = $job['date_format'] ?? null;
        $dirs = Collection::wrap($job['dir'])->filter();
        $patterns = Collection::wrap($job['pattern'] ?? '*')->filter();
        $excludes = Collection::wrap($job['exclude'] ?? null ?: [])->filter()->values();
        $maxSize = $job['max_size'] ?? null;
        $keepSize = $job['keep_size'] ?? null;
        $keepVersions = intval($job['keep_versions'] ?? 30);
        $archiveDir = $job['archive_dir'] ?? null;

        $result = static::rotateFiles(
            $dirs,
            $patterns,
            $excludes,
            $dateFormat,
            $skipEmpty,
            $maxSize,
            $keepSize,
            $keepVersions,
            $archiveDir
        );

        if ('trim' === ($result['mode'] ?? 'rotate')) {
            $this->info(sprintf(
                'In directory "%s" (match "%s") find %d files: trimmed %d, unchanged %d, warn %d, error %d.',
                $dirs->implode(', '),
                $patterns->implode(', '),
                $result['found'],
                $result['trimmed'],
                $result['unchanged'],
                $result['warn'],
                $result['error']
            ));
        } else {
            $this->info(sprintf(
                'In directory "%s" that match "%s" find %s files%s, skip %s empty files, rotated %s files%s.',
                $dirs->implode(', '),
                $patterns->implode(', '),
                $result['found'],
                0 >= $result['found'] ? '' : sprintf(' (%s)', Collection::make($result['files'])->map(function (SplFileInfo $file) use ($dirs) {
                    return $dirs->count() > 1 ? $file->getRealPath() : $file->getBasename();
                })->implode(', ')),
                $result['skipped'],
                $result['rotated'],
                0 >= $result['rotated'] ? '' : sprintf(' (%s)', Collection::make($result['items'])->map(function ($item) use ($dirs) {
                    if ($dirs->count() > 1) {
                        return sprintf('[%s => %s]', $item['path'], $item['date_path']);
                    } else {
                        return sprintf('[%s => %s]', File::basename($item['path']), File::basename($item['date_path']));
                    }
                })->implode(', '))
            ));
        }
    }

    /**
     * 流转指定目录匹配的文件 (支持全量轮转模式与头部截断 trim 模式).
     *
     * @param  string|array|Collection  $dirs
     * @param  string|array|Collection  $patterns
     * @param  string|array|Collection  $excludes
     * @param  string|null  $dateFormat
     * @param  bool  $skipEmpty
     * @param  int|string|null  $maxSize
     * @param  int|string|null  $keepSize
     * @param  int  $keepVersions
     * @param  string|null  $archiveDir
     * @return array
     *
     * @throws Throwable
     */
    public static function rotateFiles(
        $dirs,
        $patterns = '*',
        $excludes = [],
        ?string $dateFormat = null,
        bool $skipEmpty = true,
        $maxSize = null,
        $keepSize = null,
        int $keepVersions = 30,
        ?string $archiveDir = null
    ): array {
        $dirs = Collection::wrap($dirs)->filter();
        $patterns = Collection::wrap($patterns ?: '*')->filter();
        $excludes = Collection::wrap($excludes ?: [])->filter()->values();

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

        $filesCount = $files instanceof Finder ? $files->count() : count($files);
        $maxBytes = static::parseBytes($maxSize);
        $keepBytes = static::parseBytes($keepSize);

        // 如果配置了 maxSize，执行头部截断式轮转 (trim 模式)
        if (null !== $maxBytes && $maxBytes > 0) {
            $dateFormat = $dateFormat ?: 'YmdHis';
            $items = [];
            $trimmedCount = 0;
            $unchangedCount = 0;
            $warnCount = 0;
            $errorCount = 0;

            foreach ($files as $file) {
                $res = static::trimFile(
                    $file->getRealPath(),
                    $maxBytes,
                    $keepBytes,
                    $archiveDir,
                    $keepVersions,
                    $dateFormat
                );
                $items[] = $res;
                if ('trimmed' === $res['status']) {
                    $trimmedCount++;
                } elseif ('unchanged' === $res['status']) {
                    $unchangedCount++;
                } elseif ('warn' === $res['status']) {
                    $warnCount++;
                } else {
                    $errorCount++;
                }
            }

            return [
                'mode' => 'trim',
                'dirs' => $dirs->values()->all(),
                'patterns' => $patterns->values()->all(),
                'found' => $filesCount,
                'trimmed' => $trimmedCount,
                'unchanged' => $unchangedCount,
                'warn' => $warnCount,
                'error' => $errorCount,
                'files' => $files,
                'items' => $items,
            ];
        }

        // 默认全量轮转模式 (rotate 模式)
        $dateFormat = $dateFormat ?: 'Y-m-d';
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
                static::rotateFile($file->getRealPath(), $datePath);
                $rotates = $rotates->add(['path' => $file->getRealPath(), 'date_path' => $datePath]);
            }
        }

        return [
            'mode' => 'rotate',
            'dirs' => $dirs->values()->all(),
            'patterns' => $patterns->values()->all(),
            'found' => $filesCount,
            'rotated' => $rotates->count(),
            'skipped' => $filesCount - $rotates->count(),
            'files' => $files,
            'items' => $rotates->all(),
        ];
    }

    /**
     * 复制并清空源文件 (传统全量轮转).
     *
     * @param  string  $from
     * @param  string  $to
     *
     * @throws Exception
     */
    public static function rotateFile($from, $to): void
    {
        $handle = $dateHandle = null;

        try {
            $handle = fopen($from, 'r+b');
            if (! is_resource($handle)) {
                throw new Exception('Failed to open source file!');
            }

            $dateHandle = fopen($to, 'ab');
            if (! is_resource($dateHandle)) {
                throw new Exception('Failed to open destination date file!');
            }
        } catch (Throwable $exception) {
            is_resource($handle) and fclose($handle);
            is_resource($dateHandle) and fclose($dateHandle);

            throw $exception;
        }

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

            $perms = 0644;
            if (false !== ($filePerms = fileperms($from))) {
                $perms = ($filePerms & 0666) ?: 0644;
                @chmod($from, $perms);
            }
            @chmod($to, $perms);
        }
    }

    /**
     * 针对单个文件执行头部截断式归档轮转 (保留最近段，归档最老段).
     *
     * 核心安全与高可用保证:
     * 1. 目标不存在 -> 返回 missing, 不留痕;
     * 2. 绝不截断行: 向前逐块扫描最近换行 (\n), 归档段以 \n 结尾, 保留段以整行开头;
     * 3. 归档原子落位: 先写 .part 临时文件, 校验长度与 fsync 通过后才 rename;
     * 4. 原地截断: 单句柄移动保留段至文件头部, 并收敛补抄并发新追加段后 ftruncate;
     * 5. 归档自动清理: 按时间戳保留最新 keepVersions 份历史归档.
     *
     * @param  string  $file  待轮转的目标文件绝对路径
     * @param  int  $maxBytes  触发截断的大小阈值 (字节)
     * @param  int|null  $keepBytes  原地保留的最新字节数 (为 null 时默认保留 maxBytes / 2)
     * @param  string|null  $archiveDir  冷归档目录 (为 null 时默认与目标文件同级)
     * @param  int  $keepVersions  归档保留份数 (默认 30)
     * @param  string  $dateFormat  归档时间戳格式 (默认 YmdHis)
     * @param  bool  $allowSplit  无换行符时是否允许硬切字节 (默认 false)
     * @param  int  $scanMax  向前搜索换行符的最大字节数 (默认 32MB)
     * @return array
     */
    public static function trimFile(
        string $file,
        int $maxBytes,
        ?int $keepBytes = null,
        ?string $archiveDir = null,
        int $keepVersions = 30,
        string $dateFormat = 'YmdHis',
        bool $allowSplit = false,
        int $scanMax = 33554432
    ): array {
        if (! File::exists($file)) {
            return [
                'status' => 'missing',
                'file' => $file,
                'before' => null,
                'after' => null,
                'message' => 'file not found',
            ];
        }

        $fp = @fopen($file, 'r+b');
        if (! is_resource($fp)) {
            return [
                'status' => 'error',
                'file' => $file,
                'before' => null,
                'after' => null,
                'message' => 'failed to open file for read/write',
            ];
        }

        try {
            if (! flock($fp, LOCK_EX)) {
                return [
                    'status' => 'busy',
                    'file' => $file,
                    'before' => null,
                    'after' => null,
                    'message' => 'file is locked by another process',
                ];
            }

            clearstatcache(true, $file);
            $size = fstat($fp)['size'];
            if ($size < $maxBytes) {
                return [
                    'status' => 'unchanged',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => sprintf('below trigger %s, no rotation', static::formatBytes($maxBytes)),
                ];
            }

            $keepBytes = (null !== $keepBytes && $keepBytes > 0) ? $keepBytes : (int) floor($maxBytes / 2);
            if ($size <= $keepBytes) {
                return [
                    'status' => 'unchanged',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => 'size <= keepBytes, no rotation',
                ];
            }

            $target = $size - $keepBytes;

            // 1. 回溯搜索行边界
            $pos = $target;
            $scanned = 0;
            $stepWin = 32768;
            $cut = null;

            while ($pos > 0 && $scanned < $scanMax) {
                $step = min($stepWin, $pos, $scanMax - $scanned);
                $base = $pos - $step;
                fseek($fp, $base, SEEK_SET);
                $chunk = fread($fp, $step);
                if (false === $chunk || strlen($chunk) !== $step) {
                    break;
                }
                $lastLf = strrpos($chunk, "\n");
                if (false !== $lastLf) {
                    $cut = $base + $lastLf + 1;
                    break;
                }
                $pos = $base;
                $scanned += $step;
            }

            $warn = false;
            if (null === $cut || $cut <= 0) {
                if ($allowSplit) {
                    $cut = $target;
                    $warn = true;
                } else {
                    return [
                        'status' => 'warn',
                        'file' => $file,
                        'before' => $size,
                        'after' => $size,
                        'message' => sprintf('no newline within %s, skipped to avoid splitting a line', static::formatBytes($scanMax)),
                    ];
                }
            }

            // 2. 准备归档目录与文件名
            $archDir = $archiveDir ?: dirname($file);
            File::ensureDirectoryExists($archDir);
            if (! is_writable($archDir)) {
                return [
                    'status' => 'error',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => sprintf('archive directory "%s" is not writable', $archDir),
                ];
            }

            $stem = pathinfo($file, PATHINFO_FILENAME);
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $extSuffix = $ext ? ('.' . $ext) : '';
            $archFilename = sprintf('%s-%s%s', $stem, Carbon::now()->format($dateFormat ?: 'YmdHis'), $extSuffix);
            $archPath = $archDir . DIRECTORY_SEPARATOR . $archFilename;
            $partPath = $archPath . '.part';

            // 3. 写入最老 cut 字节并原子 rename
            if (File::exists($partPath)) {
                @unlink($partPath);
            }
            $partFp = @fopen($partPath, 'wb');
            if (! is_resource($partFp)) {
                return [
                    'status' => 'error',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => sprintf('cannot create part archive file: %s', $partPath),
                ];
            }

            fseek($fp, 0, SEEK_SET);
            $copied = stream_copy_to_stream($fp, $partFp, $cut);
            fflush($partFp);
            fclose($partFp);

            clearstatcache(true, $partPath);
            if ($copied !== $cut || filesize($partPath) !== $cut) {
                @unlink($partPath);
                return [
                    'status' => 'error',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => 'archive write failed (size mismatch); live untouched',
                ];
            }

            $perms = 0644;
            if (false !== ($filePerms = fileperms($file))) {
                $perms = ($filePerms & 0666) ?: 0644;
                @chmod($file, $perms);
            }
            @chmod($partPath, $perms);

            if (! @rename($partPath, $archPath)) {
                @unlink($partPath);
                return [
                    'status' => 'error',
                    'file' => $file,
                    'before' => $size,
                    'after' => $size,
                    'message' => 'failed to rename part archive file to target',
                ];
            }
            @chmod($archPath, $perms);

            // 4. 原地流式覆写并截断源文件 (低内存占用: 64KB 块流式拷贝)
            $readPos = $cut;
            $writePos = 0;
            $chunkSize = 65536;

            while ($readPos < $size) {
                $toRead = min($chunkSize, $size - $readPos);
                fseek($fp, $readPos, SEEK_SET);
                $chunk = fread($fp, $toRead);
                if (false === $chunk || strlen($chunk) !== $toRead) {
                    return [
                        'status' => 'error',
                        'file' => $file,
                        'before' => $size,
                        'after' => $size,
                        'message' => 'failed to read kept segment from source file',
                    ];
                }
                fseek($fp, $writePos, SEEK_SET);
                fwrite($fp, $chunk);
                $readPos += $toRead;
                $writePos += $toRead;
            }

            // 追写并发新追加内容 (最多 100 轮)
            $rounds = 0;
            while ($rounds < 100) {
                clearstatcache(true, $file);
                $curStat = fstat($fp);
                $curSize = $curStat['size'];
                $expectedSize = $cut + $writePos;
                if ($curSize <= $expectedSize) {
                    break;
                }
                fseek($fp, $expectedSize, SEEK_SET);
                $more = stream_get_contents($fp);
                if (false !== $more && strlen($more) > 0) {
                    fseek($fp, $writePos, SEEK_SET);
                    fwrite($fp, $more);
                    $writePos += strlen($more);
                }
                $rounds++;
            }

            ftruncate($fp, $writePos);
            $afterSize = $writePos;

            // 5. 归档版本清理
            static::pruneArchives($archDir, $stem, $ext, $keepVersions);

            $msg = sprintf('archived oldest %s to %s', static::formatBytes($cut), basename($archPath));
            if ($warn) {
                $msg .= ' (byte-split explicitly allowed)';
            }
            if ($rounds > 0) {
                $msg .= sprintf('; replayed %d append round(s)', $rounds);
            }

            return [
                'status' => $warn ? 'warn' : 'trimmed',
                'file' => $file,
                'before' => $size,
                'after' => $afterSize,
                'archive_file' => $archPath,
                'cut_bytes' => $cut,
                'message' => $msg,
            ];
        } finally {
            if (is_resource($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    /**
     * 清理指定目录下的陈旧归档文件 (保留最新 keepVersions 份).
     */
    public static function pruneArchives(string $archDir, string $stem, ?string $ext = null, int $keepVersions = 30): int
    {
        if (! File::exists($archDir) || $keepVersions <= 0) {
            return 0;
        }

        $extSuffix = $ext ? ('.' . $ext) : '';
        $pattern = sprintf('/^%s-\d{8,14}%s$/', preg_quote($stem, '/'), preg_quote($extSuffix, '/'));

        $files = [];
        foreach (File::files($archDir) as $file) {
            $name = $file->getFilename();
            if (preg_match($pattern, $name)) {
                $files[] = $file;
            } elseif (Str::endsWith($name, '.part') && Carbon::createFromTimestamp($file->getMTime())->addHours(1)->isPast()) {
                @unlink($file->getRealPath());
            }
        }

        usort($files, function (SplFileInfo $a, SplFileInfo $b) {
            return strcmp($b->getFilename(), $a->getFilename());
        });

        $deleted = 0;
        if (count($files) > $keepVersions) {
            $toDelete = array_slice($files, $keepVersions);
            foreach ($toDelete as $f) {
                if (@unlink($f->getRealPath())) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * 解析人类可读体积格式 (10M, 5MB, 1024K, 1G) 为字节数.
     *
     * @param  int|string|null  $val
     * @return int|null
     */
    public static function parseBytes($val): ?int
    {
        if (null === $val || '' === $val) {
            return null;
        }
        if (is_numeric($val)) {
            return (int) $val;
        }
        $val = strtoupper(trim(strval($val)));
        if (Str::endsWith($val, 'IB')) {
            $val = substr($val, 0, -2) . 'B';
        }
        if (Str::endsWith($val, 'B') && strlen($val) > 1 && ! Str::endsWith($val, ['KB', 'MB', 'GB', 'TB'])) {
            $val = substr($val, 0, -1);
        }
        $units = [
            'K' => 1024,
            'KB' => 1024,
            'M' => 1024 * 1024,
            'MB' => 1024 * 1024,
            'G' => 1024 * 1024 * 1024,
            'GB' => 1024 * 1024 * 1024,
            'T' => 1024 * 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024,
        ];
        foreach ($units as $unit => $multiplier) {
            if (Str::endsWith($val, $unit)) {
                $num = floatval(trim(substr($val, 0, -strlen($unit))));

                return (int) ($num * $multiplier);
            }
        }

        return (int) $val;
    }

    /**
     * 格式化字节数为人性化字符串 (1024 进制).
     */
    public static function formatBytes(?int $bytes): string
    {
        if (null === $bytes) {
            return '-';
        }
        if ($bytes >= 1073741824) {
            return sprintf('%.1fG', $bytes / 1073741824);
        }
        if ($bytes >= 1048576) {
            return sprintf('%.1fM', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return sprintf('%.1fK', $bytes / 1024);
        }

        return sprintf('%dB', $bytes);
    }

    /**
     * @throws Exception
     * @deprecated 请使用 static::rotateFile
     */
    protected function rotate($from, $to)
    {
        static::rotateFile($from, $to);
    }
}
