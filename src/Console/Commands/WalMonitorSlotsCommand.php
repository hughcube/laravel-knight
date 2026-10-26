<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use HughCube\Laravel\Knight\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalMonitorSlotsCommand extends Command
{
    protected $signature = 'wal:monitor-slots
        {--connection= : Database connection name}
        {--threshold=1024 : WAL retention threshold in MB, alert when exceeded (default: 1024MB = 1GB)}';

    protected $description = 'Monitor PostgreSQL WAL replication slots, alert when retained WAL exceeds threshold';

    public function handle(): void
    {
        $thresholdMB = max(1, intval($this->option('threshold')));
        $thresholdBytes = $thresholdMB * 1024 * 1024;

        $this->info(sprintf('Checking WAL replication slots (threshold: %dMB)...', $thresholdMB));

        try {
            $slots = $this->querySlots();
        } catch (Throwable $e) {
            $message = sprintf(
                '[wal:monitor-slots] Failed to query replication slots: %s',
                $e->getMessage()
            );
            $this->error($message);
            Log::error($message);

            return;
        }

        if (empty($slots)) {
            $this->info('No replication slots found.');

            return;
        }

        $hasAlert = false;

        foreach ($slots as $slot) {
            $slotName = $slot->slot_name;
            $plugin = isset($slot->plugin) ? ($slot->plugin ?: '-') : '-';
            $slotType = isset($slot->slot_type) ? $slot->slot_type : '-';
            $active = $slot->active ? 'active' : 'inactive';
            $activePid = isset($slot->active_pid) ? ($slot->active_pid ?: 'none') : 'none';
            $database = isset($slot->database) ? ($slot->database ?: '-') : '-';
            $restartLsn = isset($slot->restart_lsn) ? ($slot->restart_lsn ?: '-') : '-';
            $confirmedFlushLsn = isset($slot->confirmed_flush_lsn)
                ? ($slot->confirmed_flush_lsn ?: '-') : '-';
            $walStatus = isset($slot->wal_status) ? ($slot->wal_status ?: '-') : '-';

            $retainedBytes = isset($slot->retained_bytes)
                ? intval($slot->retained_bytes) : 0;
            $retainedMB = round($retainedBytes / 1024 / 1024, 2);

            $detail = sprintf(
                'slot_type: %s, plugin: %s, database: %s, active: %s,'
                .' active_pid: %s, restart_lsn: %s,'
                .' confirmed_flush_lsn: %s, wal_status: %s',
                $slotType,
                $plugin,
                $database,
                $active,
                $activePid,
                $restartLsn,
                $confirmedFlushLsn,
                $walStatus
            );

            if ($retainedBytes >= $thresholdBytes) {
                $hasAlert = true;
                $message = sprintf(
                    '[wal:monitor-slots] ALERT: slot "%s" retained WAL %.2fMB exceeds threshold %dMB (%s)',
                    $slotName,
                    $retainedMB,
                    $thresholdMB,
                    $detail
                );

                $this->error($message);
                Log::error($message);
            } else {
                $this->line(sprintf('  [OK] slot "%s": %.2fMB (%s)', $slotName, $retainedMB, $detail));
            }
        }

        if (! $hasAlert) {
            $this->info(sprintf('All %d slot(s) are within threshold.', count($slots)));
        }
    }

    /**
     * Query all replication slots with their retained WAL size.
     *
     * @return array
     */
    protected function querySlots(): array
    {
        $connection = $this->getConnection();

        return $connection->select(
            'SELECT
                slot_name,
                plugin,
                slot_type,
                database,
                active,
                active_pid,
                restart_lsn,
                confirmed_flush_lsn,
                wal_status,
                pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn) AS retained_bytes
            FROM pg_replication_slots
            WHERE restart_lsn IS NOT NULL'
        );
    }

    /**
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        $name = $this->option('connection');

        return app('db')->connection($name ?: null);
    }
}
