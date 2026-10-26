<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommand;
use HughCube\Laravel\Knight\Contracts\Database\HasWalHandler;
use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\HasWalHandlerTrait;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests for WalEventDispatchCommand using real PostgreSQL WAL.
 *
 * These tests require:
 * - PostgreSQL with wal_level = logical
 * - wal2json plugin installed
 */
class WalEventDispatchIntegrationTest extends TestCase
{
    /** @var string */
    protected $slotName;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isPgsqlConfigured()) {
            return;
        }

        $this->slotName = 'knight_integration_'.getmypid().'_'.time();

        // Create test table in PostgreSQL
        $connection = $this->app['db']->connection('pgsql');

        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_items');
        } catch (\Throwable $e) {
            // ignore
        }

        $connection->statement('
            CREATE TABLE wal_integration_items (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL DEFAULT \'\',
                updated_at TIMESTAMP NULL
            )
        ');
    }

    protected function tearDown(): void
    {
        if ($this->isPgsqlConfigured()) {
            $connection = $this->app['db']->connection('pgsql');

            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$this->slotName]);
            } catch (\Throwable $e) {
                // slot may not exist
            }

            try {
                $connection->statement('DROP TABLE IF EXISTS wal_integration_items');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        parent::tearDown();
    }

    /**
     * @return WalEventDispatchCommand
     */
    protected function makeCommand(array $options = [])
    {
        $defaults = ['--connection' => 'pgsql', '--slot' => $this->slotName];
        $options = array_merge($defaults, $options);

        $command = new WalEventDispatchCommand();
        $command->setLaravel($this->app);

        $input = new ArrayInput($options, $command->getDefinition());
        self::setProperty($command, 'input', $input);

        $bufferedOutput = new BufferedOutput();
        self::setProperty($command, 'output', new OutputStyle($input, $bufferedOutput));

        return $command;
    }

    protected function skipIfWal2jsonNotAvailable(): void
    {
        try {
            $connection = $this->app['db']->connection('pgsql');
            $tmpSlot = 'knight_wal2json_chk_'.time();

            $connection->statement(
                "SELECT pg_create_logical_replication_slot(?, 'wal2json')",
                [$tmpSlot]
            );
            $connection->statement('SELECT pg_drop_replication_slot(?)', [$tmpSlot]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('wal2json is not available: '.$e->getMessage());
        }
    }

    // ==================== End-to-End WAL Tests ====================

    public function testPollChangesDetectsInsert()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();

        // Create replication slot
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert data AFTER slot creation (so WAL captures it)
        $connection->table('wal_integration_items')->insert(['name' => 'item1']);
        $connection->table('wal_integration_items')->insert(['name' => 'item2']);

        // Build handlers for the table
        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        // Listen for dispatched records
        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        // Poll changes
        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges, 'pollChanges should detect INSERT changes');
        $this->assertNotEmpty($dispatchedRecords, 'wal:* event should be dispatched');

        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
    }

    public function testPollChangesDetectsUpdate()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        // Insert data BEFORE slot
        $connection->table('wal_integration_items')->insert(['name' => 'before_slot']);

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Update the row AFTER slot creation
        $connection->table('wal_integration_items')->where('id', 1)->update(['name' => 'updated_name']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);
        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains(1, $ids);
    }

    public function testPollChangesDetectsDelete()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        // Ensure REPLICA IDENTITY is FULL so wal2json captures oldkeys for DELETE
        $connection->statement('ALTER TABLE wal_integration_items REPLICA IDENTITY FULL');

        // Insert data BEFORE slot
        $connection->table('wal_integration_items')->insert(['name' => 'to_delete']);

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Delete the row AFTER slot creation
        $connection->table('wal_integration_items')->where('id', 1)->delete();

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);
        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains(1, $ids);
    }

    public function testPollChangesReturnsNoChangesWhenEmpty()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        // No data changes since slot creation
        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertFalse($hasChanges);
    }

    public function testPollChangesIgnoresUnregisteredTables()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert into our table but don't register a handler for it
        $connection->table('wal_integration_items')->insert(['name' => 'untracked']);

        // Empty handlers - no table registered
        $handlers = [];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertFalse($hasChanges);
        $this->assertEmpty($dispatchedRecords);
    }

    public function testPollChangesWithMultipleHandlersForSameTable()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        $connection->table('wal_integration_items')->insert(['name' => 'multi_handler']);

        $handler1 = new WalIntegrationItem();
        $handler2 = new WalIntegrationItemAlias();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler1, 'keyName' => 'id'],
                ['handler' => $handler2, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        // Both handlers should produce records (same WAL change, different modelClass)
        $this->assertCount(2, $dispatchedRecords);
        $modelClasses = array_map(function ($r) {
            return get_class($r->toModel());
        }, $dispatchedRecords);
        $this->assertCount(2, array_unique($modelClasses));
    }

    public function testPollChangesPreservesAllRecords()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert then update the same row -> generates 2 WAL entries for id=1
        $connection->table('wal_integration_items')->insert(['name' => 'original']);
        $connection->table('wal_integration_items')->where('id', 1)->update(['name' => 'modified']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        // Both insert and update records should be preserved (no dedup)
        $this->assertGreaterThanOrEqual(2, count($dispatchedRecords));
        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains(1, $ids);
    }

    public function testPollChangesWithPartitionMap()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        // Create a "partition-like" table (simulating partition mapping)
        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_items_2024');
        } catch (\Throwable $e) {
            // ignore
        }

        $connection->statement('
            CREATE TABLE wal_integration_items_2024 (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL DEFAULT \'\'
            )
        ');

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert into the "partition" table
        $connection->table('wal_integration_items_2024')->insert(['name' => 'partitioned']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        // Partition map: wal_integration_items_2024 -> wal_integration_items
        $partitionMap = [
            'wal_integration_items_2024' => 'wal_integration_items',
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, $partitionMap,
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);

        // Cleanup
        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_items_2024');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function testPollChangesWithAdvanceMode()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand(['--mode' => 'advance']);
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        $connection->table('wal_integration_items')->insert(['name' => 'advance_test']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        // First poll should detect changes
        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);
        $this->assertTrue($hasChanges);

        // Second poll should NOT detect changes (LSN was advanced)
        $hasChanges2 = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);
        $this->assertFalse($hasChanges2);
    }

    public function testPollChangesWithPeekModeRetainsChanges()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        // mode=peek is read-only and should keep WAL entries available
        $command = $this->makeCommand(['--mode' => 'peek']);
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        $connection->table('wal_integration_items')->insert(['name' => 'no_advance_test']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        // First poll should detect changes
        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);
        $this->assertTrue($hasChanges);

        // Second poll should STILL detect changes (slot not advanced in peek mode)
        $hasChanges2 = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);
        $this->assertTrue($hasChanges2);
    }

    public function testPollChangesBatchLimit()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert multiple rows
        for ($i = 1; $i <= 5; $i++) {
            $connection->table('wal_integration_items')->insert(['name' => 'batch_'.$i]);
        }

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        // Poll with batch=2 (only peek first 2 WAL entries)
        self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 2, [],
        ]);

        $this->assertNotEmpty($dispatchedRecords);
        // With batch=2, may not see all 5 IDs
        $this->assertLessThanOrEqual(5, count($dispatchedRecords));
    }

    public function testFullEndToEndWithListenerIntegration()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert data
        $connection->table('wal_integration_items')->insert(['name' => 'e2e_test']);

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $modelChangedCount = 0;
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$modelChangedCount) {
            $record = $payload[0];
            $model = $record->toModel();
            if (method_exists($model, 'onKnightModelChanged')) {
                $model->onKnightModelChanged();
            }
            $modelChangedCount++;
        });

        WalIntegrationItem::$modelChangedCount = 0;

        self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertGreaterThanOrEqual(1, $modelChangedCount);
        $this->assertGreaterThanOrEqual(1, WalIntegrationItem::$modelChangedCount);
    }

    public function testPollChangesWithUuidPrimaryKey()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_uuid_items');
        } catch (\Throwable $e) {
            // ignore
        }

        $connection->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        $connection->statement('
            CREATE TABLE wal_integration_uuid_items (
                uuid UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                name VARCHAR(255) NOT NULL DEFAULT \'\'
            )
        ');

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert and capture the UUID
        $connection->statement("INSERT INTO wal_integration_uuid_items (name) VALUES ('uuid_test')");
        $row = $connection->selectOne('SELECT uuid FROM wal_integration_uuid_items LIMIT 1');
        $insertedUuid = $row->uuid;

        $handler = new WalIntegrationUuidItem();
        $handlers = [
            'wal_integration_uuid_items' => [
                ['handler' => $handler, 'keyName' => 'uuid'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);
        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains($insertedUuid, $ids);

        // Cleanup
        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_uuid_items');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function testPollChangesWithMixedOperations()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        // Set REPLICA IDENTITY FULL for DELETE tracking
        $connection->statement('ALTER TABLE wal_integration_items REPLICA IDENTITY FULL');

        // Insert before slot
        $connection->table('wal_integration_items')->insert(['name' => 'pre_existing']);

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // After slot: INSERT + UPDATE + DELETE on different records
        $connection->table('wal_integration_items')->insert(['name' => 'new_item']);       // id=2
        $connection->table('wal_integration_items')->where('id', 1)->update(['name' => 'updated']); // update id=1
        $connection->table('wal_integration_items')->where('id', 1)->delete();             // delete id=1

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);

        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertContains(1, $ids);  // updated + deleted
        $this->assertContains(2, $ids);  // inserted
    }

    public function testPollChangesWithLargePayload()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert many rows to generate a large WAL payload
        for ($i = 1; $i <= 100; $i++) {
            $connection->table('wal_integration_items')->insert(['name' => str_repeat('x', 200)]);
        }

        $handler = new WalIntegrationItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        $hasChanges = self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        $this->assertTrue($hasChanges);
        $this->assertNotEmpty($dispatchedRecords);
        $ids = array_map(function ($r) {
            return $r->getId();
        }, $dispatchedRecords);
        $this->assertCount(100, $ids);

        // Verify all 100 IDs are present
        for ($i = 1; $i <= 100; $i++) {
            $this->assertContains($i, $ids);
        }
    }

    public function testPollChangesDispatchesCorrectHandlerPerTable()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');

        // Create a second table
        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_other');
        } catch (\Throwable $e) {
            // ignore
        }
        $connection->statement('
            CREATE TABLE wal_integration_other (
                id SERIAL PRIMARY KEY,
                value TEXT NOT NULL DEFAULT \'\'
            )
        ');

        $command = $this->makeCommand();
        self::callMethod($command, 'ensureSlotExists', [$this->slotName]);

        // Insert into both tables
        $connection->table('wal_integration_items')->insert(['name' => 'table1']);
        $connection->table('wal_integration_other')->insert(['value' => 'table2']);

        $handler1 = new WalIntegrationItem();
        $handler2 = new WalIntegrationOtherItem();
        $handlers = [
            'wal_integration_items' => [
                ['handler' => $handler1, 'keyName' => 'id'],
            ],
            'wal_integration_other' => [
                ['handler' => $handler2, 'keyName' => 'id'],
            ],
        ];

        $dispatchedRecords = [];
        Event::listen('wal:*', function (string $eventName, array $payload) use (&$dispatchedRecords) {
            $dispatchedRecords[] = $payload[0];
        });

        self::callMethod($command, 'pollChanges', [
            $this->slotName, $handlers, 1000, [],
        ]);

        // Should have 2 records, one for each table
        $this->assertCount(2, $dispatchedRecords);

        $modelClasses = array_map(function ($r) {
            return get_class($r->toModel());
        }, $dispatchedRecords);
        $this->assertContains(WalIntegrationItem::class, $modelClasses);
        $this->assertContains(WalIntegrationOtherItem::class, $modelClasses);

        // Cleanup
        try {
            $connection->statement('DROP TABLE IF EXISTS wal_integration_other');
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

/**
 * @property int $id
 * @property string $name
 */
class WalIntegrationItem extends Model implements HasWalHandler
{
    use HasWalHandlerTrait;

    protected $connection = 'pgsql';

    protected $table = 'wal_integration_items';

    protected $fillable = ['id', 'name'];

    public $timestamps = false;

    /** @var int */
    public static $modelChangedCount = 0;

    public function onKnightModelChanged(): void
    {
        static::$modelChangedCount++;
    }
}

/**
 * Second handler for the same table (testing multi-handler dispatch).
 */
class WalIntegrationItemAlias extends Model implements HasWalHandler
{
    use HasWalHandlerTrait;

    protected $connection = 'pgsql';

    protected $table = 'wal_integration_items';

    protected $fillable = ['id', 'name'];

    public $timestamps = false;

    public function onKnightModelChanged(): void
    {
    }
}

/**
 * UUID-based model for testing non-integer primary keys.
 *
 * @property string $uuid
 * @property string $name
 */
class WalIntegrationUuidItem extends Model implements HasWalHandler
{
    use HasWalHandlerTrait;

    protected $connection = 'pgsql';

    protected $table = 'wal_integration_uuid_items';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function onKnightModelChanged(): void
    {
    }
}

/**
 * Handler for a separate table (testing multi-table dispatch).
 *
 * @property int $id
 * @property string $value
 */
class WalIntegrationOtherItem extends Model implements HasWalHandler
{
    use HasWalHandlerTrait;

    protected $connection = 'pgsql';

    protected $table = 'wal_integration_other';

    protected $fillable = ['id', 'value'];

    public $timestamps = false;

    public function onKnightModelChanged(): void
    {
    }
}
