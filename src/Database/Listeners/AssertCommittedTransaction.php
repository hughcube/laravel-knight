<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/1/31
 * Time: 11:46.
 */

namespace HughCube\Laravel\Knight\Database\Listeners;

use HughCube\Laravel\Knight\Exceptions\NotCloseTransactionException;
use HughCube\Laravel\Knight\Traits\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

class AssertCommittedTransaction
{
    use Container;

    /**
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function handle($event)
    {
        foreach ($this->getConnections() as $connection) {
            /** @phpstan-ignore-next-line */
            if (method_exists($connection, 'transactionLevel') && $connection->transactionLevel() > 0) {
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }

                throw new NotCloseTransactionException(
                    $connection,
                    sprintf("Connection '%s' transaction is not closed!", $connection->getName())
                );
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
