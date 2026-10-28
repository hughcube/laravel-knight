<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 */

namespace HughCube\Laravel\Knight\Database\Listeners;

use HughCube\Laravel\Knight\Traits\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class EnsureTransactionsAreRolledBack
{
    use Container;

    /**
     * @param mixed $event
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function handle($event): void
    {
        if (! $this->getContainer()->resolved('db')) {
            return;
        }

        foreach ($this->getConnections() as $connection) {
            /** @phpstan-ignore-next-line */
            if (method_exists($connection, 'transactionLevel') && $connection->transactionLevel() > 0) {
                $level = $connection->transactionLevel();

                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }

                Log::critical(sprintf(
                    "Octane Request Terminated with active transaction on connection '%s' (uncommitted levels: %d). Force rollBack executed to prevent state pollution!",
                    $connection->getName(),
                    $level
                ));
            }
        }
    }

    /**
     * @return array<string, Connection>
     *
     * @throws BindingResolutionException
     */
    protected function getConnections(): array
    {
        return $this->getDatabaseManager()->getConnections();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function getDatabaseManager(): DatabaseManager
    {
        return $this->getContainer()->make('db');
    }
}
