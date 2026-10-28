<?php

namespace HughCube\Laravel\Knight\Tests\Database\Listeners;

use HughCube\Laravel\Knight\Database\Listeners\EnsureTransactionsAreRolledBack;
use HughCube\Laravel\Knight\Tests\TestCase;

class EnsureTransactionsAreRolledBackTest extends TestCase
{
    public function testHandlePassesWhenNoOpenTransaction()
    {
        $listener = new EnsureTransactionsAreRolledBack();

        $this->assertNoException(function () use ($listener) {
            $listener->handle(null);
        });
    }

    public function testHandleRollsBackWhenTransactionOpen()
    {
        $connection = $this->app->make('db')->connection();
        $connection->beginTransaction();
        $this->assertSame(1, $connection->transactionLevel());

        $listener = new EnsureTransactionsAreRolledBack();
        $listener->handle(null);

        $this->assertSame(0, $connection->transactionLevel());
    }
}
