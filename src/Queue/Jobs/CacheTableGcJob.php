<?php

namespace HughCube\Laravel\Knight\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CacheTableGcJob extends Job
{
    public function rules(): array
    {
        return [
            'connection' => ['string', 'nullable'],
            'cache_table' => ['nullable'],
            'lock' => ['nullable'],
        ];
    }

    protected function action(): void
    {
        $this->info(sprintf('共删除caches %s条!', $this->gc($this->getCacheTable())));

        $this->info(sprintf('共删除cache locks %s条!', $this->gc($this->getLockTable())));
    }

    protected function gc($table, $subSeconds = 1000): int
    {
        if (null === $table) {
            return 0;
        }

        $expiration = Carbon::now()->subSeconds($subSeconds)->getTimestamp();

        $doneCount = 0;
        $query = $this->getConnection()->table($table)->where('expiration', '<=', $expiration);
        while (true) {
            $doneCount += ($n = $query->clone()->limit(200)->delete());
            if (0 >= $n) {
                break;
            }
        }

        return $doneCount;
    }

    protected function getConnection(): Connection
    {
        /** @var Connection */
        return DB::connection($this->p('connection'));
    }

    protected function getCacheTable(): ?string
    {
        $table = $this->p('cache_table');

        return false === $table ? null : ($table ?: 'cache_locks');
    }

    protected function getLockTable(): ?string
    {
        $table = $this->p('lock_table');

        return false === $table ? null : ($table ?: 'cache_locks');
    }
}
