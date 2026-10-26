<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\WalMonitorSlotsCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class WalMonitorSlotsCommandTest extends TestCase
{
    // ==================== Command Registration ====================

    public function testCommandIsRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('wal:monitor-slots', $commands);
    }

    public function testCommandSignature()
    {
        $exitCode = Artisan::call('wal:monitor-slots', ['--help' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('--connection', $output);
        $this->assertStringContainsString('--threshold', $output);
    }

    // ==================== Default Threshold ====================

    public function testDefaultThresholdIs1024MB()
    {
        $command = new WalMonitorSlotsCommand();
        $this->initializeCommand($command);

        $threshold = $command->getDefinition()->getOption('threshold')->getDefault();
        $this->assertSame('1024', $threshold);
    }

    // ==================== Handle: No Slots ====================

    public function testHandleWithNoSlots()
    {
        $command = $this->makeCommandWithMockConnection([]);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('No replication slots found', $output);
    }

    // ==================== Handle: All Slots Within Threshold ====================

    public function testHandleAllSlotsWithinThreshold()
    {
        $slots = [
            $this->makeSlot('slot_a', [
                'plugin' => 'wal2json',
                'active' => true,
                'active_pid' => 12345,
                'retained_bytes' => 500 * 1024 * 1024,
            ]),
            $this->makeSlot('slot_b', [
                'plugin' => 'pgoutput',
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 100 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "slot_a"', $output);
        $this->assertStringContainsString('[OK] slot "slot_b"', $output);
        $this->assertStringContainsString('All 2 slot(s) are within threshold', $output);
        $this->assertStringNotContainsString('ALERT', $output);
    }

    // ==================== Handle: Slot Exceeds Threshold ====================

    public function testHandleSlotExceedsThresholdLogsError()
    {
        $slots = [
            $this->makeSlot('big_slot', [
                'plugin' => 'wal2json',
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 2 * 1024 * 1024 * 1024,
                'restart_lsn' => '0/1500000',
                'confirmed_flush_lsn' => '0/1400000',
                'wal_status' => 'reserved',
                'database' => 'mydb',
            ]),
        ];

        $logHandler = $this->setupTestLogHandler();

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('ALERT', $output);
        $this->assertStringContainsString('big_slot', $output);
        $this->assertStringContainsString('exceeds threshold 1024MB', $output);
        $this->assertStringContainsString('wal2json', $output);
        $this->assertStringContainsString('inactive', $output);
        $this->assertStringContainsString('restart_lsn: 0/1500000', $output);
        $this->assertStringContainsString('confirmed_flush_lsn: 0/1400000', $output);
        $this->assertStringContainsString('wal_status: reserved', $output);
        $this->assertStringContainsString('database: mydb', $output);

        $this->assertLogContains($logHandler, 'error', 'big_slot');
    }

    // ==================== Handle: Mixed Slots ====================

    public function testHandleMixedSlots()
    {
        $slots = [
            $this->makeSlot('ok_slot', [
                'active' => true,
                'active_pid' => 111,
                'retained_bytes' => 50 * 1024 * 1024,
            ]),
            $this->makeSlot('bad_slot', [
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 1500 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "ok_slot"', $output);
        $this->assertStringContainsString('ALERT', $output);
        $this->assertStringContainsString('bad_slot', $output);
        $this->assertStringNotContainsString('All', $output);
    }

    // ==================== Handle: Exact Threshold Boundary ====================

    public function testHandleSlotAtExactThresholdTriggersAlert()
    {
        $slots = [
            $this->makeSlot('boundary_slot', [
                'active' => true,
                'active_pid' => 999,
                'retained_bytes' => 1024 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('ALERT', $output);
        $this->assertStringContainsString('boundary_slot', $output);
    }

    public function testHandleSlotJustBelowThresholdIsOk()
    {
        $slots = [
            $this->makeSlot('under_slot', [
                'active' => true,
                'active_pid' => 888,
                'retained_bytes' => 1024 * 1024 * 1024 - 1,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "under_slot"', $output);
        $this->assertStringNotContainsString('ALERT', $output);
    }

    // ==================== Handle: Custom Threshold ====================

    public function testHandleWithCustomThreshold()
    {
        $slots = [
            $this->makeSlot('small_slot', [
                'active' => true,
                'active_pid' => 100,
                'retained_bytes' => 300 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots, ['--threshold' => '256']);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('threshold: 256MB', $output);
        $this->assertStringContainsString('ALERT', $output);
        $this->assertStringContainsString('exceeds threshold 256MB', $output);
    }

    // ==================== Handle: Query Exception ====================

    public function testHandleQueryExceptionLogsError()
    {
        $logHandler = $this->setupTestLogHandler();

        $command = $this->makeCommandWithFailingConnection(
            new \RuntimeException('connection refused')
        );
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('Failed to query replication slots', $output);
        $this->assertStringContainsString('connection refused', $output);

        $this->assertLogContains($logHandler, 'error', 'Failed to query replication slots');
    }

    // ==================== Handle: Null / Missing Fields ====================

    public function testHandleSlotWithNullPlugin()
    {
        $slots = [
            $this->makeSlot('physical_slot', [
                'plugin' => null,
                'slot_type' => 'physical',
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 10 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "physical_slot"', $output);
        $this->assertStringContainsString('plugin: -', $output);
        $this->assertStringContainsString('slot_type: physical', $output);
    }

    public function testHandleSlotWithNullRetainedBytes()
    {
        $slots = [
            $this->makeSlot('null_bytes_slot', [
                'active' => true,
                'active_pid' => 555,
                'retained_bytes' => null,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "null_bytes_slot"', $output);
        $this->assertStringContainsString('0.00MB', $output);
    }

    public function testHandleSlotWithNullLsnFields()
    {
        $slots = [
            $this->makeSlot('null_lsn_slot', [
                'active' => false,
                'retained_bytes' => 2 * 1024 * 1024 * 1024,
                'restart_lsn' => null,
                'confirmed_flush_lsn' => null,
                'wal_status' => null,
                'database' => null,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('ALERT', $output);
        $this->assertStringContainsString('restart_lsn: -', $output);
        $this->assertStringContainsString('confirmed_flush_lsn: -', $output);
        $this->assertStringContainsString('wal_status: -', $output);
        $this->assertStringContainsString('database: -', $output);
    }

    // ==================== Handle: Active Status Display ====================

    public function testOkLineShowsAllDetailFields()
    {
        $slots = [
            $this->makeSlot('ok_detail_slot', [
                'active' => true,
                'active_pid' => 200,
                'retained_bytes' => 10 * 1024 * 1024,
                'slot_type' => 'logical',
                'plugin' => 'wal2json',
                'database' => 'staging',
                'restart_lsn' => '0/CC000000',
                'confirmed_flush_lsn' => '0/CC001111',
                'wal_status' => 'reserved',
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('[OK] slot "ok_detail_slot"', $output);
        $this->assertStringContainsString('slot_type: logical', $output);
        $this->assertStringContainsString('plugin: wal2json', $output);
        $this->assertStringContainsString('database: staging', $output);
        $this->assertStringContainsString('active: active', $output);
        $this->assertStringContainsString('active_pid: 200', $output);
        $this->assertStringContainsString('restart_lsn: 0/CC000000', $output);
        $this->assertStringContainsString('confirmed_flush_lsn: 0/CC001111', $output);
        $this->assertStringContainsString('wal_status: reserved', $output);
    }

    public function testHandleDisplaysActiveStatus()
    {
        $slots = [
            $this->makeSlot('active_slot', [
                'active' => true,
                'active_pid' => 777,
                'retained_bytes' => 10 * 1024 * 1024,
            ]),
            $this->makeSlot('inactive_slot', [
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 10 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('"active_slot": 10.00MB', $output);
        $this->assertStringContainsString('active: active,', $output);
        $this->assertStringContainsString('"inactive_slot": 10.00MB', $output);
        $this->assertStringContainsString('active: inactive,', $output);
    }

    public function testHandleDisplaysSlotType()
    {
        $slots = [
            $this->makeSlot('logical_slot', [
                'slot_type' => 'logical',
                'active' => true,
                'retained_bytes' => 10 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('slot_type: logical', $output);
    }

    // ==================== Handle: Alert Detail Fields ====================

    public function testAlertShowsActivePid()
    {
        $slots = [
            $this->makeSlot('pid_slot', [
                'active' => true,
                'active_pid' => 42,
                'retained_bytes' => 2 * 1024 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('active_pid: 42', $output);
    }

    public function testAlertShowsNoneWhenPidIsNull()
    {
        $slots = [
            $this->makeSlot('no_pid_slot', [
                'active' => false,
                'active_pid' => null,
                'retained_bytes' => 2 * 1024 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('active_pid: none', $output);
    }

    public function testAlertShowsAllLsnAndStatusFields()
    {
        $slots = [
            $this->makeSlot('detail_slot', [
                'active' => true,
                'active_pid' => 100,
                'retained_bytes' => 2 * 1024 * 1024 * 1024,
                'restart_lsn' => '0/AABB0000',
                'confirmed_flush_lsn' => '0/AABB1111',
                'wal_status' => 'extended',
                'database' => 'production',
                'slot_type' => 'logical',
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('slot_type: logical', $output);
        $this->assertStringContainsString('database: production', $output);
        $this->assertStringContainsString('restart_lsn: 0/AABB0000', $output);
        $this->assertStringContainsString('confirmed_flush_lsn: 0/AABB1111', $output);
        $this->assertStringContainsString('wal_status: extended', $output);
        $this->assertStringContainsString('active_pid: 100', $output);
    }

    // ==================== Handle: Threshold Minimum ====================

    public function testThresholdMinimumIsOneMB()
    {
        $slots = [
            $this->makeSlot('tiny_slot', [
                'active' => false,
                'retained_bytes' => 2 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots, ['--threshold' => '0']);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('threshold: 1MB', $output);
        $this->assertStringContainsString('ALERT', $output);
    }

    public function testNegativeThresholdClampedToOneMB()
    {
        $slots = [
            $this->makeSlot('neg_slot', [
                'active' => false,
                'retained_bytes' => 2 * 1024 * 1024,
            ]),
        ];

        $command = $this->makeCommandWithMockConnection($slots, ['--threshold' => '-100']);
        $output = $this->executeAndGetOutput($command);

        $this->assertStringContainsString('threshold: 1MB', $output);
    }

    // ==================== querySlots SQL ====================

    public function testQuerySlotsExecutesCorrectSql()
    {
        $connection = new QueryCapturingConnection();

        $command = $this->makeCommandWithConnection($connection);
        self::callMethod($command, 'querySlots');

        $query = $connection->lastQuery;
        $this->assertNotNull($query);
        $this->assertStringContainsString('pg_replication_slots', $query);
        $this->assertStringContainsString('pg_wal_lsn_diff', $query);
        $this->assertStringContainsString('pg_current_wal_lsn()', $query);
        $this->assertStringContainsString('restart_lsn', $query);
        $this->assertStringContainsString('confirmed_flush_lsn', $query);
        $this->assertStringContainsString('wal_status', $query);
        $this->assertStringContainsString('database', $query);
        $this->assertStringContainsString('retained_bytes', $query);
        $this->assertStringContainsString('slot_name', $query);
        $this->assertStringContainsString('active_pid', $query);
    }

    // ==================== getConnection ====================

    public function testGetConnectionUsesDefaultWhenNoOption()
    {
        $command = new WalMonitorSlotsCommand();
        $this->initializeCommand($command);

        $connection = self::callMethod($command, 'getConnection');
        $this->assertNotNull($connection);
    }

    // ==================== Helpers ====================

    /**
     * Build a slot object with defaults.
     *
     * @param  string  $name
     * @param  array  $overrides
     * @return object
     */
    private function makeSlot($name, array $overrides = [])
    {
        $defaults = [
            'slot_name' => $name,
            'plugin' => 'wal2json',
            'slot_type' => 'logical',
            'database' => 'testdb',
            'active' => false,
            'active_pid' => null,
            'restart_lsn' => '0/1000000',
            'confirmed_flush_lsn' => '0/1000000',
            'wal_status' => 'reserved',
            'retained_bytes' => 0,
        ];

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * @param  array  $slots
     * @param  array  $options
     * @return WalMonitorSlotsCommand
     */
    private function makeCommandWithMockConnection(array $slots, array $options = [])
    {
        $connection = new class($slots)
        {
            private $slots;

            public function __construct(array $slots)
            {
                $this->slots = $slots;
            }

            public function select($query, array $bindings = [])
            {
                return $this->slots;
            }
        };

        return $this->makeCommandWithConnection($connection, $options);
    }

    /**
     * @param  \Throwable  $exception
     * @param  array  $options
     * @return WalMonitorSlotsCommand
     */
    private function makeCommandWithFailingConnection(
        \Throwable $exception,
        array $options = []
    ) {
        $connection = new class($exception)
        {
            private $exception;

            public function __construct(\Throwable $exception)
            {
                $this->exception = $exception;
            }

            public function select($query, array $bindings = [])
            {
                throw $this->exception;
            }
        };

        return $this->makeCommandWithConnection($connection, $options);
    }

    /**
     * @param  object  $connection
     * @param  array  $options
     * @return WalMonitorSlotsCommand
     */
    private function makeCommandWithConnection($connection, array $options = [])
    {
        $command = new class() extends WalMonitorSlotsCommand
        {
            public $mockConnection = null;

            protected function getConnection()
            {
                if ($this->mockConnection !== null) {
                    return $this->mockConnection;
                }

                return parent::getConnection();
            }
        };

        $command->mockConnection = $connection;
        $this->initializeCommand($command, $options);

        return $command;
    }

    private function initializeCommand(
        WalMonitorSlotsCommand $command,
        array $options = []
    ): WalMonitorSlotsCommand {
        $command->setLaravel($this->app);

        $input = new ArrayInput($options, $command->getDefinition());
        self::setProperty($command, 'input', $input);

        $bufferedOutput = new BufferedOutput();
        self::setProperty(
            $command,
            'output',
            new OutputStyle($input, $bufferedOutput)
        );

        return $command;
    }

    private function executeAndGetOutput(WalMonitorSlotsCommand $command): string
    {
        $command->handle();

        /** @var OutputStyle $outputStyle */
        $outputStyle = self::getProperty($command, 'output');

        $reflection = new \ReflectionClass($outputStyle);
        $outputProp = $reflection->getProperty('output');
        $outputProp->setAccessible(true);

        /** @var BufferedOutput $buffered */
        $buffered = $outputProp->getValue($outputStyle);

        return $buffered->fetch();
    }
}

class QueryCapturingConnection
{
    /** @var string|null */
    public $lastQuery = null;

    public function select($query, array $bindings = [])
    {
        $this->lastQuery = $query;

        return [];
    }
}
