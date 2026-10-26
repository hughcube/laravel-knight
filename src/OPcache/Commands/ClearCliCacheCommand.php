<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/2/22
 * Time: 11:18.
 */

namespace HughCube\Laravel\Knight\OPcache\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ClearCliCacheCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected $signature = 'opcache:clear-cli-cache';

    /**
     * @inheritdoc
     */
    protected $description = 'Clear OPcache for CLI environment';

    /**
     * @param  Schedule  $schedule
     * @return void
     *
     * @throws Exception
     */
    public function handle(Schedule $schedule)
    {
        $startTime = microtime(true);

        if (! extension_loaded('Zend OPcache')) {
            $this->error('You do not have the Zend OPcache extension loaded, sample data is being shown instead.');

            Log::warning(sprintf(
                'OPcache CLI clear failed: extension not loaded - Command: %s, User: %s',
                $this->signature,
                get_current_user()
            ));

            return;
        }

        if (false === opcache_get_status()) {
            $this->warn('OPcache is not enabled.');

            Log::warning(sprintf(
                'OPcache CLI clear failed: not enabled - Command: %s, User: %s',
                $this->signature,
                get_current_user()
            ));

            return;
        }

        if (! opcache_reset()) {
            throw new Exception('Failed to reset OPcache.');
        }

        $duration = microtime(true) - $startTime;

        Log::info(sprintf(
            'OPcache CLI cleared - Command: %s, User: %s, Duration: %sms',
            $this->signature,
            get_current_user(),
            round($duration * 1000, 2)
        ));

        $this->info('The OPcache is reset successfully.');
    }
}
