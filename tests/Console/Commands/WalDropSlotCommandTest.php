<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class WalDropSlotCommandTest extends TestCase
{
    // ==================== Command Registration ====================

    public function testCommandIsRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('wal:drop-slot', $commands);
    }

    public function testCommandSignature()
    {
        $exitCode = Artisan::call('wal:drop-slot', ['slot' => 'test', '--help' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('slot', $output);
        $this->assertStringContainsString('--connection', $output);
    }

    // ==================== PostgreSQL-dependent tests ====================

    public function testDropNonExistentSlotShowsError()
    {
        $this->skipIfPgsqlNotConfigured();

        $exitCode = Artisan::call('wal:drop-slot', [
            'slot' => 'nonexistent_slot_'.time(),
            '--connection' => 'pgsql',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('does not exist', $output);
    }

    public function testDropExistingSlot()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $slotName = 'knight_test_drop_'.time();
        $connection = $this->app['db']->connection('pgsql');

        try {
            // Create a slot first
            $connection->statement(
                "SELECT pg_create_logical_replication_slot(?, 'wal2json')",
                [$slotName]
            );

            // Verify it exists
            $exists = $connection->selectOne(
                'SELECT 1 FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );
            $this->assertNotNull($exists);

            // Drop it
            Artisan::call('wal:drop-slot', [
                'slot' => $slotName,
                '--connection' => 'pgsql',
            ]);

            $output = Artisan::output();
            $this->assertStringContainsString('dropped successfully', $output);

            // Verify it's gone
            $exists = $connection->selectOne(
                'SELECT 1 FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );
            $this->assertNull($exists);
        } catch (\Throwable $e) {
            // Cleanup if test fails
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $inner) {
                // ignore
            }

            throw $e;
        }
    }

    /**
     * Skip if wal2json is not available on the PostgreSQL server.
     */
    protected function skipIfWal2jsonNotAvailable(): void
    {
        try {
            $connection = $this->app['db']->connection('pgsql');
            $slotName = 'knight_wal2json_check_'.time();

            $connection->statement(
                "SELECT pg_create_logical_replication_slot(?, 'wal2json')",
                [$slotName]
            );

            $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('wal2json is not available: '.$e->getMessage());
        }
    }
}
