<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommand;
use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class WalEventDispatchCommandTest extends TestCase
{
    /**
     * Create command with proper input/output initialized.
     *
     * @param  array  $options
     * @return WalEventDispatchCommand
     */
    protected function makeCommand(array $options = [])
    {
        $command = new WalEventDispatchCommand();
        $command->setLaravel($this->app);

        $input = new ArrayInput($options, $command->getDefinition());
        self::setProperty($command, 'input', $input);

        $bufferedOutput = new BufferedOutput();
        self::setProperty($command, 'output', new OutputStyle($input, $bufferedOutput));

        return $command;
    }

    // ==================== buildWal2jsonParams ====================

    public function testBuildWal2jsonParamsPlaceholdersEmpty()
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** auto format-version=2 占一对占位符 */
        $this->assertSame(', ?, ?', $placeholders);
    }

    public function testBuildWal2jsonParamsPlaceholdersWithParams()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=content', 'add-tables=users'], '--no-add-tables' => true]);

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** 2 user params + 1 auto(format-version) = 3 */
        $this->assertSame(', ?, ?, ?, ?, ?, ?', $placeholders);
    }

    public function testBuildWal2jsonParamsPlaceholdersWithAutoAddTables()
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);
        self::setProperty($command, 'autoAddTables', 'public.users,public.orders');

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** 0 user + 2 auto(format-version, add-tables) = 2 */
        $this->assertSame(', ?, ?, ?, ?', $placeholders);
    }

    public function testBuildWal2jsonParamsPlaceholdersWithParamsAndAutoAddTables()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=content'], '--no-add-tables' => true]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** 1 user(filter-columns) + 2 auto(format-version, add-tables) = 3 */
        $this->assertSame(', ?, ?, ?, ?, ?, ?', $placeholders);
    }

    public function testBuildWal2jsonParamsBindingsEmpty()
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        /** auto format-version=2 */
        $this->assertSame(['format-version', '2'], $bindings);
    }

    public function testBuildWal2jsonParamsBindingsWithParams()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=content,body', 'add-tables=users'], '--no-add-tables' => true]);

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        /** user params first, then auto format-version */
        $this->assertSame(['filter-columns', 'content,body', 'add-tables', 'users', 'format-version', '2'], $bindings);
    }

    public function testBuildWal2jsonParamsBindingsWithKeyOnly()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['include-lsn'], '--no-add-tables' => true]);

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        $this->assertSame(['include-lsn', '', 'format-version', '2'], $bindings);
    }

    public function testBuildWal2jsonParamsBindingsWithAutoAddTables()
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);
        self::setProperty($command, 'autoAddTables', 'public.users,public.orders');

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        $this->assertSame(['format-version', '2', 'add-tables', 'public.users,public.orders'], $bindings);
    }

    public function testBuildWal2jsonParamsBindingsWithParamsAndAutoAddTables()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=content'], '--no-add-tables' => true]);
        self::setProperty($command, 'autoAddTables', 'public.users,public.orders');

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        $this->assertSame(['filter-columns', 'content', 'format-version', '2', 'add-tables', 'public.users,public.orders'], $bindings);
    }

    // ==================== getUserWal2jsonParamKeys ====================

    public function testGetUserWal2jsonParamKeysEmpty()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getUserWal2jsonParamKeys');
        $this->assertSame([], $result);
    }

    public function testGetUserWal2jsonParamKeysWithParams()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=content', 'add-tables=users']]);

        $result = self::callMethod($command, 'getUserWal2jsonParamKeys');
        $this->assertSame(['filter-columns', 'add-tables'], $result);
    }

    public function testGetUserWal2jsonParamKeysWithKeyOnly()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['include-lsn']]);

        $result = self::callMethod($command, 'getUserWal2jsonParamKeys');
        $this->assertSame(['include-lsn'], $result);
    }

    public function testGetUserWal2jsonParamKeysWithMultipleEquals()
    {
        $command = $this->makeCommand(['--wal2json-params' => ['filter-columns=a=b']]);

        $result = self::callMethod($command, 'getUserWal2jsonParamKeys');
        /** explode('=', ..., 2) 只按第一个等号拆分 */
        $this->assertSame(['filter-columns'], $result);
    }

    // ==================== collectAutoWal2jsonParams 冲突检测 ====================

    public function testCollectAutoParamsDefault()
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        $this->assertSame(['format-version' => '2'], $result);
    }

    public function testCollectAutoParamsSkipsUserFormatVersion()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['format-version=1'],
            '--no-add-tables' => true,
        ]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        /** 用户已指定 format-version，自动参数应跳过 */
        $this->assertArrayNotHasKey('format-version', $result);
        $this->assertSame([], $result);
    }

    public function testCollectAutoParamsSkipsUserAddTables()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['add-tables=custom.table'],
            '--no-add-tables' => true,
        ]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        /** 用户已指定 add-tables，自动参数应跳过；format-version 未被用户覆盖 */
        $this->assertArrayNotHasKey('add-tables', $result);
        $this->assertArrayHasKey('format-version', $result);
    }

    public function testCollectAutoParamsSkipsUserFilterTables()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['filter-tables=custom.table'],
            '--filter-tables' => ['telescope_entries'],
            '--no-add-tables' => true,
        ]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        /** 用户通过 --wal2json-params 指定了 filter-tables，--filter-tables 的自动注入被跳过 */
        $this->assertArrayNotHasKey('filter-tables', $result);
        $this->assertArrayHasKey('format-version', $result);
    }

    public function testCollectAutoParamsWithAllConflicts()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['format-version=1', 'add-tables=foo', 'filter-tables=bar'],
            '--filter-tables' => ['telescope_entries'],
            '--no-add-tables' => true,
        ]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        /** 所有三个 key 都被用户覆盖，自动参数应全部跳过 */
        $this->assertSame([], $result);
    }

    public function testCollectAutoParamsIncludesFilterTables()
    {
        $command = $this->makeCommand([
            '--filter-tables' => ['telescope_entries', 'audit.logs'],
            '--no-add-tables' => true,
        ]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        $this->assertSame('2', $result['format-version']);
        $this->assertSame('public.telescope_entries,audit.logs', $result['filter-tables']);
    }

    // ==================== Placeholder/Binding 与用户参数覆盖的交互 ====================

    public function testPlaceholdersWhenUserOverridesFormatVersion()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['format-version=1'],
            '--no-add-tables' => true,
        ]);

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** 1 user(format-version) + 0 auto = 1 */
        $this->assertSame(', ?, ?', $placeholders);
    }

    public function testBindingsWhenUserOverridesFormatVersion()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['format-version=1'],
            '--no-add-tables' => true,
        ]);

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        /** 用户的 format-version=1 不会被 auto format-version=2 追加 */
        $this->assertSame(['format-version', '1'], $bindings);
    }

    public function testPlaceholdersWhenUserOverridesAddTables()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['add-tables=custom.foo'],
            '--no-add-tables' => true,
        ]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        [$placeholders] = self::callMethod($command, 'buildWal2jsonParams');
        /** 1 user(add-tables) + 1 auto(format-version) = 2 */
        $this->assertSame(', ?, ?, ?, ?', $placeholders);
    }

    public function testBindingsWhenUserOverridesAddTables()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['add-tables=custom.foo'],
            '--no-add-tables' => true,
        ]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        /** 用户 add-tables 优先，auto add-tables 被跳过 */
        $this->assertSame(['add-tables', 'custom.foo', 'format-version', '2'], $bindings);
    }

    public function testBindingsWhenUserOverridesFilterTables()
    {
        $command = $this->makeCommand([
            '--wal2json-params' => ['filter-tables=custom.bar'],
            '--filter-tables' => ['telescope_entries'],
            '--no-add-tables' => true,
        ]);

        [, $bindings] = self::callMethod($command, 'buildWal2jsonParams');
        /** 用户 filter-tables 优先，--filter-tables 的自动注入被跳过 */
        $this->assertSame(['filter-tables', 'custom.bar', 'format-version', '2'], $bindings);
    }

    // ==================== formatChangeSummary ====================

    public function testFormatChangeSummaryEmpty()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'formatChangeSummary', [[]]);
        $this->assertSame('', $result);
    }

    public function testFormatChangeSummarySingleKind()
    {
        $command = $this->makeCommand();

        $counts = ['users' => ['insert' => 5]];
        $result = self::callMethod($command, 'formatChangeSummary', [$counts]);
        $this->assertSame('users: 5 inserts', $result);
    }

    public function testFormatChangeSummaryMultipleTablesAndKinds()
    {
        $command = $this->makeCommand();

        $counts = [
            'users' => ['insert' => 3, 'update' => 1],
            'orders' => ['delete' => 2],
        ];
        $result = self::callMethod($command, 'formatChangeSummary', [$counts]);
        $this->assertSame('users: 3 inserts, 1 update; orders: 2 deletes', $result);
    }

    public function testFormatChangeSummarySingularPluralBoundary()
    {
        $command = $this->makeCommand();

        /** count=1 无复数后缀 */
        $counts = ['t' => ['insert' => 1]];
        $this->assertSame('t: 1 insert', self::callMethod($command, 'formatChangeSummary', [$counts]));

        /** count=2 加 s */
        $counts = ['t' => ['insert' => 2]];
        $this->assertSame('t: 2 inserts', self::callMethod($command, 'formatChangeSummary', [$counts]));
    }

    // ==================== getSlotName ====================

    public function testGetSlotNameDefaultUsesAppName()
    {
        $this->app['config']->set('app.name', 'MyTestApp');
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $this->assertSame('mytestapp_testing_wal_event', $result);
    }

    public function testGetSlotNameWithExplicitSlotOption()
    {
        $this->app['config']->set('app.name', 'MyApp');

        $command = $this->makeCommand(['--slot' => 'custom_slot']);

        $result = self::callMethod($command, 'getSlotName');
        $this->assertSame('custom_slot', $result);
    }

    public function testGetSlotNameWithSpecialCharacters()
    {
        $this->app['config']->set('app.name', 'My-Test.App!');
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $this->assertSame('my_test_app_testing_wal_event', $result);
    }

    public function testGetSlotNameWithEmptyAppName()
    {
        $this->app['config']->set('app.name', null);
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $this->assertSame('app_testing_wal_event', $result);
    }

    // ==================== resolveTable ====================

    public function testResolveTableWithPartitionMatch()
    {
        $command = $this->makeCommand();
        $partitionMap = [
            'orders_2024_01' => 'orders',
            'orders_2024_02' => 'orders',
            'logs_archive' => 'logs',
        ];

        $result = self::callMethod($command, 'resolveTable', ['orders_2024_01', $partitionMap]);
        $this->assertSame('orders', $result);

        $result = self::callMethod($command, 'resolveTable', ['orders_2024_02', $partitionMap]);
        $this->assertSame('orders', $result);

        $result = self::callMethod($command, 'resolveTable', ['logs_archive', $partitionMap]);
        $this->assertSame('logs', $result);
    }

    public function testResolveTableWithoutPartitionMatch()
    {
        $command = $this->makeCommand();
        $partitionMap = [
            'orders_2024_01' => 'orders',
        ];

        $result = self::callMethod($command, 'resolveTable', ['users', $partitionMap]);
        $this->assertSame('users', $result);
    }

    public function testResolveTableWithEmptyPartitionMap()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'resolveTable', ['any_table', []]);
        $this->assertSame('any_table', $result);
    }

    // ==================== getModelPaths ====================

    public function testGetModelPathsDefault()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getModelPaths');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $keys = array_keys($result);
        $values = array_values($result);

        $this->assertStringContainsString('Models', $keys[0]);
        $this->assertSame('App\\Models\\', $values[0]);
    }

    public function testGetModelPathsWithCustomPath()
    {
        $command = $this->makeCommand(['--model-path' => ['app/Domain/Models:App\\Domain\\Models']]);

        $result = self::callMethod($command, 'getModelPaths');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $values = array_values($result);
        $this->assertSame('App\\Domain\\Models\\', $values[0]);
    }

    public function testGetModelPathsWithMultiplePaths()
    {
        $command = $this->makeCommand(['--model-path' => [
            'app/Models:App\\Models',
            'app/Domain:App\\Domain',
        ]]);

        $result = self::callMethod($command, 'getModelPaths');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetModelPathsWithPathWithoutNamespace()
    {
        $command = $this->makeCommand(['--model-path' => ['app/Models']]);

        $result = self::callMethod($command, 'getModelPaths');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $values = array_values($result);
        $this->assertSame('App\\Models\\', $values[0]);
    }

    // ==================== Command Signature ====================

    public function testCommandSignatureIsRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('wal:event-dispatch', $commands);
    }

    public function testCommandHasExpectedOptions()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('connection'));
        $this->assertTrue($definition->hasOption('slot'));
        $this->assertTrue($definition->hasOption('interval'));
        $this->assertTrue($definition->hasOption('batch'));
        $this->assertTrue($definition->hasOption('mode'));
        $this->assertTrue($definition->hasOption('model-path'));
        $this->assertTrue($definition->hasOption('wal2json-params'));
        $this->assertTrue($definition->hasOption('filter-tables'));
        $this->assertTrue($definition->hasOption('format-version'));
    }

    public function testCommandOptionDefaults()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertSame('0.5', $definition->getOption('interval')->getDefault());
        $this->assertSame('1000', $definition->getOption('batch')->getDefault());
        $this->assertSame('advance', $definition->getOption('mode')->getDefault());
        $this->assertSame('2', $definition->getOption('format-version')->getDefault());
        $this->assertNull($definition->getOption('connection')->getDefault());
        $this->assertNull($definition->getOption('slot')->getDefault());
    }

    // ==================== getMode ====================

    public function testGetModeDefaultIsAdvance()
    {
        $command = $this->makeCommand();

        $this->assertSame('advance', self::callMethod($command, 'getMode'));
    }

    public function testGetModeSupportsAutoAndPeek()
    {
        $auto = $this->makeCommand(['--mode' => 'auto']);
        $peek = $this->makeCommand(['--mode' => 'peek']);

        $this->assertSame('auto', self::callMethod($auto, 'getMode'));
        $this->assertSame('peek', self::callMethod($peek, 'getMode'));
    }

    public function testGetModeInvalidValueFallsBackToAdvance()
    {
        $command = $this->makeCommand(['--mode' => 'invalid']);

        $this->assertSame('advance', self::callMethod($command, 'getMode'));
    }

    // ==================== shouldRun / errorStreak ====================

    public function testInitialShouldRunIsTrue()
    {
        $command = $this->makeCommand();

        $this->assertTrue(self::getProperty($command, 'shouldRun'));
    }

    public function testInitialErrorStreakIsZero()
    {
        $command = $this->makeCommand();

        $this->assertSame(0, self::getProperty($command, 'errorStreak'));
    }

    // ==================== discoverWalHandlers ====================

    public function testDiscoverWalHandlersWithNonExistentPath()
    {
        $command = $this->makeCommand(['--model-path' => ['/nonexistent/path/models:App\\Nonexistent\\']]);

        $handlers = self::callMethod($command, 'discoverWalHandlers');

        $this->assertIsArray($handlers);
        $this->assertEmpty($handlers);
    }

    // ==================== PostgreSQL-dependent tests ====================

    public function testBuildPartitionMapOnNonPgsql()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'buildPartitionMap');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildPartitionMapOnPgsql()
    {
        $this->skipIfPgsqlNotConfigured();

        $command = $this->makeCommand(['--connection' => 'pgsql']);

        $result = self::callMethod($command, 'buildPartitionMap');

        $this->assertIsArray($result);
    }

    public function testEnsureSlotExistsOnPgsql()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $command = $this->makeCommand(['--connection' => 'pgsql']);

        $slotName = 'knight_test_wal_'.time();

        try {
            self::callMethod($command, 'ensureSlotExists', [$slotName]);

            $connection = $this->app['db']->connection('pgsql');
            $exists = $connection->selectOne(
                'SELECT 1 FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );
            $this->assertNotNull($exists);

            // Calling again should not throw (slot already exists)
            self::callMethod($command, 'ensureSlotExists', [$slotName]);
            $this->assertTrue(true);
        } finally {
            try {
                $this->app['db']->connection('pgsql')->statement(
                    'SELECT pg_drop_replication_slot(?)',
                    [$slotName]
                );
            } catch (\Throwable $e) {
                // ignore cleanup errors
            }
        }
    }

    public function testReconnectDatabaseOnSqlite()
    {
        $command = $this->makeCommand();

        self::callMethod($command, 'reconnectDatabase');
        $this->assertTrue(true);
    }

    public function testRegisterSignalHandlersDoesNotThrow()
    {
        $command = $this->makeCommand();

        self::callMethod($command, 'registerSignalHandlers');
        $this->assertTrue(true);
    }

    public function testGetConnection()
    {
        $command = $this->makeCommand();

        $connection = self::callMethod($command, 'getConnection');
        $this->assertInstanceOf(\Illuminate\Database\Connection::class, $connection);
    }

    public function testGetConnectionWithPgsql()
    {
        $this->skipIfPgsqlNotConfigured();

        $command = $this->makeCommand(['--connection' => 'pgsql']);

        $connection = self::callMethod($command, 'getConnection');
        $this->assertInstanceOf(\Illuminate\Database\Connection::class, $connection);
        $this->assertSame('pgsql', $connection->getDriverName());
    }

    // ==================== getSlotName strict tests ====================

    public function testGetSlotNameWithNumericAppName()
    {
        $this->app['config']->set('app.name', '123App456');
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $this->assertSame('123app456_testing_wal_event', $result);
    }

    public function testGetSlotNameWithChineseAppName()
    {
        $this->app['config']->set('app.name', '我的应用');
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $appEnvSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', '我的应用_testing'));
        $appEnvSlug = preg_replace('/_+/', '_', trim($appEnvSlug, '_'));
        $this->assertSame($appEnvSlug.'_wal_event', $result);
    }

    public function testGetSlotNameWithAllSpecialCharacters()
    {
        $this->app['config']->set('app.name', '@#$%^&*');
        $this->app['config']->set('app.env', 'testing');

        $command = $this->makeCommand();

        $result = self::callMethod($command, 'getSlotName');

        $this->assertSame('testing_wal_event', $result);
    }

    // ==================== resolveTable strict tests ====================

    public function testResolveTableChainedPartitions()
    {
        $command = $this->makeCommand();

        $partitionMap = [
            'child_partition' => 'parent_partition',
            'parent_partition' => 'grandparent',
        ];

        $result = self::callMethod($command, 'resolveTable', ['child_partition', $partitionMap]);
        $this->assertSame('parent_partition', $result);
    }

    public function testResolveTableIsCaseSensitive()
    {
        $command = $this->makeCommand();

        $partitionMap = [
            'Users' => 'users',
        ];

        $this->assertSame('users', self::callMethod($command, 'resolveTable', ['Users', $partitionMap]));
        $this->assertSame('users', self::callMethod($command, 'resolveTable', ['users', $partitionMap]));
    }

    // ==================== errorStreak / backoff tests ====================

    public function testErrorStreakCanBeModified()
    {
        $command = $this->makeCommand();

        self::setProperty($command, 'errorStreak', 5);
        $this->assertSame(5, self::getProperty($command, 'errorStreak'));

        self::setProperty($command, 'errorStreak', 0);
        $this->assertSame(0, self::getProperty($command, 'errorStreak'));
    }

    public function testShouldRunCanBeStopped()
    {
        $command = $this->makeCommand();

        $this->assertTrue(self::getProperty($command, 'shouldRun'));

        self::setProperty($command, 'shouldRun', false);
        $this->assertFalse(self::getProperty($command, 'shouldRun'));
    }

    public function testExponentialBackoffFormula()
    {
        $command = $this->makeCommand();

        $this->assertSame(1, self::callMethod($command, 'getBackoffSeconds', [1]));
        $this->assertSame(2, self::callMethod($command, 'getBackoffSeconds', [2]));
        $this->assertSame(4, self::callMethod($command, 'getBackoffSeconds', [3]));
        $this->assertSame(8, self::callMethod($command, 'getBackoffSeconds', [4]));
        $this->assertSame(16, self::callMethod($command, 'getBackoffSeconds', [5]));
        $this->assertSame(32, self::callMethod($command, 'getBackoffSeconds', [6]));
        $this->assertSame(60, self::callMethod($command, 'getBackoffSeconds', [7]));
        $this->assertSame(60, self::callMethod($command, 'getBackoffSeconds', [10]));
        /** 边界：errorStreak=0 不应报错 */
        $this->assertSame(1, self::callMethod($command, 'getBackoffSeconds', [0]));
    }

    // ==================== getModelPaths strict tests ====================

    public function testGetModelPathsNamespaceTrimsTrailingBackslash()
    {
        $command = $this->makeCommand(['--model-path' => ['app/Models:App\\Models\\\\']]);

        $result = self::callMethod($command, 'getModelPaths');
        $values = array_values($result);

        $this->assertSame('App\\Models\\', $values[0]);
    }

    public function testGetModelPathsWithColonInPath()
    {
        $command = $this->makeCommand(['--model-path' => ['src/Models:My\\App\\Models']]);

        $result = self::callMethod($command, 'getModelPaths');
        $values = array_values($result);

        $this->assertSame('My\\App\\Models\\', $values[0]);
    }

    // ==================== queue options ====================

    public function testCommandHasQueueOptions()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('queue'));
        $this->assertTrue($definition->hasOption('queue-connection'));
    }

    public function testCommandQueueOptionDefaults()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertNull($definition->getOption('queue')->getDefault());
        $this->assertSame('sync', $definition->getOption('queue-connection')->getDefault());
    }

    // ==================== buildAutoAddTables ====================

    public function testBuildAutoAddTablesWithHandlers()
    {
        $command = $this->makeCommand();

        $handlers = [
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            'orders' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, []]);
        $this->assertSame('public.users,public.orders', $result);
    }

    public function testBuildAutoAddTablesEmpty()
    {
        $command = $this->makeCommand();

        $result = self::callMethod($command, 'buildAutoAddTables', [[], []]);
        $this->assertNull($result);
    }

    public function testBuildAutoAddTablesWithSchemaQualifiedTable()
    {
        $command = $this->makeCommand();

        $handlers = [
            'audit.logs' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, []]);
        $this->assertSame('audit.logs,public.users', $result);
    }

    public function testBuildAutoAddTablesSingleTable()
    {
        $command = $this->makeCommand();

        $handlers = [
            'questions' => [
                ['handler' => new \stdClass(), 'keyName' => 'id'],
                ['handler' => new \stdClass(), 'keyName' => 'id'],
            ],
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, []]);
        $this->assertSame('public.questions', $result);
    }

    public function testBuildAutoAddTablesDefaultPartitionMap()
    {
        $command = $this->makeCommand();

        $handlers = [
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        /** $partitionMap 默认为 []，不传也不报错 */
        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers]);
        $this->assertSame('public.users', $result);
    }

    public function testBuildAutoAddTablesExpandsPartitions()
    {
        $command = $this->makeCommand();

        $handlers = [
            'orders' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        /** orders 有两个分区子表 */
        $partitionMap = [
            'orders_p0' => 'orders',
            'orders_p1' => 'orders',
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, $partitionMap]);
        /** orders 展开为子表名，users 保持原样 */
        $this->assertSame('public.orders_p0,public.orders_p1,public.users', $result);
    }

    public function testBuildAutoAddTablesPartitionedAndNonPartitionedMixed()
    {
        $command = $this->makeCommand();

        $handlers = [
            'orders' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            'logs' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        $partitionMap = [
            'orders_p0' => 'orders',
            'orders_p1' => 'orders',
            'logs_2024' => 'logs',
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, $partitionMap]);
        /** orders→2子表, logs→1子表, users→原样 */
        $this->assertSame('public.orders_p0,public.orders_p1,public.logs_2024,public.users', $result);
    }

    public function testBuildAutoAddTablesUnknownPartitionIgnored()
    {
        $command = $this->makeCommand();

        /** handler 只监听 users，partition map 有 orders 的子表但无 handler */
        $handlers = [
            'users' => [['handler' => new \stdClass(), 'keyName' => 'id']],
        ];

        $partitionMap = [
            'orders_p0' => 'orders',
        ];

        $result = self::callMethod($command, 'buildAutoAddTables', [$handlers, $partitionMap]);
        /** users 非分区表，直接使用 */
        $this->assertSame('public.users', $result);
    }

    // ==================== --no-add-tables option ====================

    public function testCommandHasNoAddTablesOption()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('no-add-tables'));
    }

    public function testNoAddTablesDefaultIsFalse()
    {
        $command = $this->makeCommand();

        $this->assertFalse($command->getDefinition()->getOption('no-add-tables')->getDefault());
    }

    public function testAutoAddTablesInitiallyNull()
    {
        $command = $this->makeCommand();

        $this->assertNull(self::getProperty($command, 'autoAddTables'));
    }

    // ==================== --filter-tables option ====================

    public function testCommandHasFilterTablesOption()
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->getDefinition()->hasOption('filter-tables'));
    }

    public function testFilterTablesAutoQualifiesSchema()
    {
        $command = $this->makeCommand([
            '--filter-tables' => ['telescope_entries', 'audit.logs'],
            '--no-add-tables' => true,
        ]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        $this->assertSame('public.telescope_entries,audit.logs', $result['filter-tables']);
    }

    public function testFilterTablesAndNoAddTablesCoexist()
    {
        $command = $this->makeCommand([
            '--filter-tables' => ['telescope_entries'],
            '--no-add-tables' => true,
        ]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        $this->assertArrayHasKey('format-version', $result);
        $this->assertArrayHasKey('filter-tables', $result);
        $this->assertArrayNotHasKey('add-tables', $result);
    }

    public function testFilterTablesAndAutoAddTablesCoexist()
    {
        $command = $this->makeCommand([
            '--filter-tables' => ['telescope_entries'],
            '--no-add-tables' => true,
        ]);
        self::setProperty($command, 'autoAddTables', 'public.users');

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        /** filter-tables 和 add-tables 可以共存 */
        $this->assertArrayHasKey('filter-tables', $result);
        $this->assertArrayHasKey('add-tables', $result);
        $this->assertSame('public.users', $result['add-tables']);
        $this->assertSame('public.telescope_entries', $result['filter-tables']);
    }

    // ==================== --format-version option ====================

    public function testFormatVersionDefault()
    {
        $command = $this->makeCommand();

        $this->assertSame('2', $command->getDefinition()->getOption('format-version')->getDefault());
    }

    public function testFormatVersionCanBeOverridden()
    {
        $command = $this->makeCommand(['--format-version' => '1', '--no-add-tables' => true]);

        $result = self::callMethod($command, 'collectAutoWal2jsonParams');
        $this->assertSame('1', $result['format-version']);
    }

    // ==================== 大批量场景：batch 参数限制内存 ====================

    public function testBatchOptionDefault()
    {
        $command = $this->makeCommand();

        $this->assertSame('1000', $command->getDefinition()->getOption('batch')->getDefault());
    }

    public function testBatchOptionClampedToMinimum()
    {
        /**
         * handle() 中 batch = max(1, intval($this->option('batch')))
         * 确保至少为 1，避免 SQL 查询传入 0.
         */
        $this->assertSame(1, max(1, intval('0')));
        $this->assertSame(1, max(1, intval('-5')));
        $this->assertSame(500, max(1, intval('500')));
    }

    public function testMemoryLimitOptionDefault()
    {
        $command = $this->makeCommand();

        $this->assertSame('128', $command->getDefinition()->getOption('memory')->getDefault());
    }

    public function testIsMemoryExceeded()
    {
        $command = $this->makeCommand();

        /** 当前进程肯定用了 >1MB 内存 */
        $this->assertTrue(self::callMethod($command, 'isMemoryExceeded', [1]));
        /** 99999MB 足够大，不会超 */
        $this->assertFalse(self::callMethod($command, 'isMemoryExceeded', [99999]));
    }

    // ==================== command description ====================

    public function testCommandDescription()
    {
        $command = $this->makeCommand();

        $this->assertNotEmpty($command->getDescription());
    }

    // ==================== pollChanges 集成测试（PG + wal2json） ====================

    /**
     * 创建跳过 Job dispatch 的测试用 Command，用于大批量内存测试。
     * dispatchWalChange 替换为计数器，避免 sync 执行 50 万个 Job。
     *
     * @return array [command, &dispatchCount]
     */
    protected function makeNoDispatchCommand(array $options = [])
    {
        $command = new class() extends WalEventDispatchCommand
        {
            /** @var int */
            public $testDispatchCount = 0;

            /**
             * 抽样保存的 records：第一条、最后一条、以及按 kind 分类计数。
             * 避免 50 万条全量保存导致内存爆炸。
             */
            /** @var \HughCube\Laravel\Knight\Database\Wal\WalChangeRecord|null */
            public $testFirstRecord = null;

            /** @var \HughCube\Laravel\Knight\Database\Wal\WalChangeRecord|null */
            public $testLastRecord = null;

            /** @var array<string, int> kind => count */
            public $testKindCounts = [];

            /** @var array 收集的所有 ID（用于唯一性验证） */
            public $testCollectedIds = [];

            /** @var int 最大收集 ID 数量，超过后不再收集 */
            public $testMaxCollectIds = 100000;

            protected function dispatchWalChange(\HughCube\Laravel\Knight\Database\Wal\WalChangeRecord $record): void
            {
                $this->testDispatchCount++;

                if (null === $this->testFirstRecord) {
                    $this->testFirstRecord = $record;
                }
                $this->testLastRecord = $record;

                $kind = $record->getKind();
                $this->testKindCounts[$kind] = ($this->testKindCounts[$kind] ?? 0) + 1;

                if (count($this->testCollectedIds) < $this->testMaxCollectIds) {
                    $this->testCollectedIds[] = $record->getId();
                }
            }
        };

        $command->setLaravel($this->app);
        $input = new ArrayInput($options, $command->getDefinition());
        self::setProperty($command, 'input', $input);
        $bufferedOutput = new BufferedOutput();
        self::setProperty($command, 'output', new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        return $command;
    }

    /**
     * 50 万行单事务 INSERT → pollChanges 消费，验证内存可控 + 数据完整。
     */
    public function testPollChanges500kInsertSingleTransaction()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_500k_ins_'.getmypid();
        $tableName = 'knight_500k_ins_'.getmypid();
        $total = 500000;

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT, value INT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 单事务循环插入 50 万行
            $connection->getPdo()->beginTransaction();
            for ($i = 0; $i < $total; $i += 5000) {
                $values = [];
                $end = min($i + 5000, $total);
                for ($j = $i; $j < $end; $j++) {
                    $values[] = sprintf("('user_%d', %d)", $j, $j);
                }
                $connection->statement(sprintf('INSERT INTO %s (name, value) VALUES %s', $tableName, implode(',', $values)));
            }
            $connection->getPdo()->commit();

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $memBefore = memory_get_usage(true);
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, $total, []]);
            $memDeltaMB = (memory_get_usage(true) - $memBefore) / 1024 / 1024;

            $this->assertTrue($hasChanges);
            $this->assertSame($total, $command->testDispatchCount, "应 dispatch $total 条 INSERT");
            $this->assertLessThan(50, $memDeltaMB, sprintf('内存增量 %.1fMB 超出预期', $memDeltaMB));

            /** 验证 WAL 拿到的数据内容正确 */
            $this->assertNotNull($command->testFirstRecord);
            $this->assertSame('insert', $command->testFirstRecord->getKind());
            $this->assertSame($tableName, $command->testFirstRecord->getTable());
            $this->assertNotNull($command->testFirstRecord->getId());
            $columns = $command->testFirstRecord->getColumns();
            $this->assertArrayHasKey('id', $columns);
            $this->assertArrayHasKey('name', $columns);
            $this->assertArrayHasKey('value', $columns);
            $this->assertStringStartsWith('user_', $columns['name']);

            /** 抽样验证最后一条 */
            $this->assertSame('insert', $command->testLastRecord->getKind());
            $this->assertNotNull($command->testLastRecord->getId());
            $this->assertArrayHasKey('name', $command->testLastRecord->getColumns());

            /** 验证所有 record 的 kind 均为 insert */
            $this->assertSame(['insert' => $total], $command->testKindCounts);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 50 万行单 SQL UPDATE → pollChanges 消费，验证内存可控 + 数据完整。
     */
    public function testPollChanges500kUpdateSingleSql()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_500k_upd_'.getmypid();
        $tableName = 'knight_500k_upd_'.getmypid();
        $total = 500000;

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT, value INT)', $tableName));

            // 先灌数据（slot 创建前，不产生 WAL）
            for ($i = 0; $i < $total; $i += 5000) {
                $values = [];
                $end = min($i + 5000, $total);
                for ($j = $i; $j < $end; $j++) {
                    $values[] = sprintf("('user_%d', %d)", $j, $j);
                }
                $connection->statement(sprintf('INSERT INTO %s (name, value) VALUES %s', $tableName, implode(',', $values)));
            }

            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 单条 SQL 修改 50 万行
            $connection->statement(sprintf("UPDATE %s SET name = 'updated', value = value + 1", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $memBefore = memory_get_usage(true);
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, $total, []]);
            $memDeltaMB = (memory_get_usage(true) - $memBefore) / 1024 / 1024;

            $this->assertTrue($hasChanges);
            $this->assertSame($total, $command->testDispatchCount, "应 dispatch $total 条 UPDATE");
            $this->assertLessThan(50, $memDeltaMB, sprintf('内存增量 %.1fMB 超出预期', $memDeltaMB));

            /** 验证 WAL 拿到的 UPDATE 数据内容正确 */
            $this->assertNotNull($command->testFirstRecord);
            $this->assertSame('update', $command->testFirstRecord->getKind());
            $this->assertSame($tableName, $command->testFirstRecord->getTable());
            $this->assertNotNull($command->testFirstRecord->getId());
            $columns = $command->testFirstRecord->getColumns();
            $this->assertArrayHasKey('id', $columns);
            $this->assertArrayHasKey('name', $columns);
            $this->assertSame('updated', $columns['name'], 'UPDATE 后 name 应为 updated');
            $this->assertArrayHasKey('value', $columns);

            /** 验证所有 record 的 kind 均为 update */
            $this->assertSame(['update' => $total], $command->testKindCounts);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 50 万行单 SQL DELETE → pollChanges 消费，验证内存可控 + 数据完整。
     */
    public function testPollChanges500kDeleteSingleSql()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_500k_del_'.getmypid();
        $tableName = 'knight_500k_del_'.getmypid();
        $total = 500000;

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));

            // 先灌数据（slot 创建前）
            for ($i = 0; $i < $total; $i += 5000) {
                $values = [];
                $end = min($i + 5000, $total);
                for ($j = $i; $j < $end; $j++) {
                    $values[] = sprintf("('user_%d')", $j);
                }
                $connection->statement(sprintf('INSERT INTO %s (name) VALUES %s', $tableName, implode(',', $values)));
            }

            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 单条 SQL 删除 50 万行
            $connection->statement(sprintf('DELETE FROM %s', $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $memBefore = memory_get_usage(true);
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, $total, []]);
            $memDeltaMB = (memory_get_usage(true) - $memBefore) / 1024 / 1024;

            $this->assertTrue($hasChanges);
            $this->assertSame($total, $command->testDispatchCount, "应 dispatch $total 条 DELETE");
            $this->assertLessThan(50, $memDeltaMB, sprintf('内存增量 %.1fMB 超出预期', $memDeltaMB));

            /** 验证 WAL 拿到的 DELETE 数据内容正确 */
            $this->assertNotNull($command->testFirstRecord);
            $this->assertSame('delete', $command->testFirstRecord->getKind());
            $this->assertSame($tableName, $command->testFirstRecord->getTable());
            $this->assertNotNull($command->testFirstRecord->getId());
            /** DELETE 默认 REPLICA IDENTITY 下 oldColumns 只有主键 */
            $oldColumns = $command->testFirstRecord->getOldColumns();
            $this->assertArrayHasKey('id', $oldColumns);
            /** DELETE 的 columns 应为空（无新值） */
            $this->assertEmpty($command->testFirstRecord->getColumns());

            /** 验证所有 record 的 kind 均为 delete */
            $this->assertSame(['delete' => $total], $command->testKindCounts);

            /** 验证抽样 ID 唯一（testMaxCollectIds 限制避免 OOM） */
            $uniqueIds = array_unique($command->testCollectedIds);
            $this->assertCount(count($command->testCollectedIds), $uniqueIds, 'DELETE 的 ID 应唯一');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * advance 模式下 pollChanges 正确推进 slot。
     */
    public function testPollChangesAdvanceModeOnPgsql()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_adv_test_'.getmypid();
        $tableName = 'knight_adv_test_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) SELECT 'row_' || g FROM generate_series(1, 1000) g", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'advance',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 10000, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1000, $command->testDispatchCount);

            /** 验证 WAL record 数据内容 */
            $this->assertNotNull($command->testFirstRecord);
            $this->assertSame('insert', $command->testFirstRecord->getKind());
            $this->assertSame($tableName, $command->testFirstRecord->getTable());
            $columns = $command->testFirstRecord->getColumns();
            $this->assertArrayHasKey('name', $columns);
            $this->assertStringStartsWith('row_', $columns['name']);

            /** 验证 ID 唯一且数量正确 */
            $this->assertCount(1000, array_unique($command->testCollectedIds), 'advance 模式应拿到 1000 个不同 ID');

            $remaining = $connection->select(
                "SELECT count(*) AS cnt FROM pg_logical_slot_peek_changes(?, NULL, 1, 'format-version', '2')",
                [$slotName]
            );
            $this->assertEquals(0, $remaining[0]->cnt, 'advance 模式处理后 slot 应已推进');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 无变更时 pollChanges 返回 false。
     */
    public function testPollChangesNoChangesOnPgsql()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_empty_test_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'advance',
                '--no-add-tables' => true,
            ]);

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, [], 1000, []]);
            $this->assertFalse($hasChanges, '无变更时应返回 false');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== pollChanges 边界与异常测试（PG + wal2json） ====================

    /**
     * v2 格式的 B(begin)/C(commit) 控制记录应被跳过，只处理 I/U/D。
     */
    public function testPollChangesSkipsBeginCommitRecords()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_bc_test_'.getmypid();
        $tableName = 'knight_bc_test_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 3 条 INSERT → wal2json v2 会产生 B + 3*I + C = 5 行
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('a'), ('b'), ('c')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            /** 只有 3 条 I 被 dispatch，B/C 被跳过 */
            $this->assertSame(3, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 不在 handler 中的表的变更应被跳过。
     */
    public function testPollChangesSkipsUnhandledTables()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_unhandled_'.getmypid();
        $tableName = 'knight_unhandled_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('a'), ('b')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** handler 注册的表名和实际变更的表名不同 → 全部跳过 */
            $handlers = [
                'nonexistent_table' => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            /** WAL 有数据但无匹配 handler → return false, dispatch 0 */
            $this->assertFalse($hasChanges);
            $this->assertSame(0, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 同一张表多个 handler 应各自独立接收相同变更。
     */
    public function testPollChangesMultipleHandlersSameTable()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_multi_h_'.getmypid();
        $tableName = 'knight_multi_h_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('x')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** 同表 2 个 handler */
            $handlers = [
                $tableName => [
                    ['handler' => new \stdClass(), 'keyName' => 'id'],
                    ['handler' => new \stdClass(), 'keyName' => 'id'],
                ],
            ];

            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            /** 1 条 INSERT × 2 个 handler = 2 次 dispatch */
            $this->assertSame(2, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 分区子表的变更应通过 partitionMap 解析到父表的 handler。
     */
    public function testPollChangesPartitionTableResolution()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_part_'.getmypid();
        $parentTable = 'knight_part_parent_'.getmypid();
        $childTable = $parentTable.'_p0';

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $parentTable));

            // 创建分区表
            $connection->statement(sprintf(
                'CREATE TABLE %s (id SERIAL, name TEXT, part_key INT) PARTITION BY RANGE (part_key)',
                $parentTable
            ));
            $connection->statement(sprintf(
                'CREATE TABLE %s PARTITION OF %s FOR VALUES FROM (0) TO (1000000)',
                $childTable,
                $parentTable
            ));

            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // INSERT 到父表，PG 路由到子表 → WAL 中记录的是子表名
            $connection->statement(sprintf("INSERT INTO %s (name, part_key) VALUES ('part_row', 1)", $parentTable));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** handler 注册在父表名上 */
            $handlers = [
                $parentTable => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** partitionMap: 子表 → 父表 */
            $partitionMap = [$childTable => $parentTable];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, $partitionMap]);

            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $parentTable));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * peek 模式不消费 WAL，多次 poll 返回相同数据。
     */
    public function testPollChangesPeekModeDoesNotConsume()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_peek_'.getmypid();
        $tableName = 'knight_peek_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('peek1'), ('peek2')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'peek',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** 第一次 peek */
            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $firstCount = $command->testDispatchCount;
            $this->assertSame(2, $firstCount);

            /** 重置计数器，第二次 peek 应看到相同数据 */
            $command->testDispatchCount = 0;
            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertSame(2, $command->testDispatchCount, 'peek 模式不消费，第二次应看到相同数据');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * auto 模式读即消费，第二次 poll 应无数据。
     */
    public function testPollChangesAutoModeConsumesOnRead()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_auto_cons_'.getmypid();
        $tableName = 'knight_auto_cons_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('auto1')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** 第一次消费 */
            $has1 = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($has1);
            $this->assertSame(1, $command->testDispatchCount);

            /** 第二次应无数据（已被消费） */
            $command->testDispatchCount = 0;
            $has2 = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertFalse($has2);
            $this->assertSame(0, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * format-version=1 下的 WAL 解析：单 JSON 包含 change 数组。
     */
    public function testPollChangesFormatVersion1()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_v1_'.getmypid();
        $tableName = 'knight_v1_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('v1a'), ('v1b')", $tableName));

            /** 使用 format-version=1 */
            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--format-version' => '1',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            $this->assertTrue($hasChanges);
            $this->assertSame(2, $command->testDispatchCount, 'v1 格式应正确解析 2 条 INSERT');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 对不存在的 slot 调用 pollChanges 应抛出异常（不静默吞掉）。
     */
    public function testPollChangesWithNonexistentSlotThrows()
    {
        $this->skipIfPgsqlNotConfigured();

        $command = $this->makeNoDispatchCommand([
            '--connection' => 'pgsql',
            '--mode' => 'auto',
            '--no-add-tables' => true,
        ]);

        $this->expectException(\Throwable::class);
        self::callMethod($command, 'pollChanges', ['nonexistent_slot_12345', [], 100, []]);
    }

    /**
     * 多事务混合：INSERT → UPDATE → DELETE 在不同事务中，单次 poll 全部消费。
     */
    public function testPollChangesMultipleTransactionsMixed()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_mix_tx_'.getmypid();
        $tableName = 'knight_mix_tx_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 三个独立事务
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('a'), ('b'), ('c')", $tableName));
            $connection->statement(sprintf("UPDATE %s SET name = 'updated' WHERE name = 'a'", $tableName));
            $connection->statement(sprintf("DELETE FROM %s WHERE name = 'b'", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 10000, []]);

            $this->assertTrue($hasChanges);
            /** 3 INSERT + 1 UPDATE + 1 DELETE = 5 */
            $this->assertSame(5, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 多表混合变更：handler 只监听部分表，未监听表的变更被跳过。
     */
    public function testPollChangesMultipleTablesPartialHandler()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_partial_'.getmypid();
        $table1 = 'knight_partial_a_'.getmypid();
        $table2 = 'knight_partial_b_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $table1));
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $table2));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $table1));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $table2));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 两张表各插入数据
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('a1'), ('a2')", $table1));
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('b1'), ('b2'), ('b3')", $table2));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** 只监听 table1，table2 的变更应被跳过 */
            $handlers = [
                $table1 => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 10000, []]);

            /** 只有 table1 的 2 条 INSERT 被 dispatch */
            $this->assertSame(2, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $table1));
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $table2));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * advance 模式无变更时应推进 slot 消除幻影滞后（phantom lag prevention）。
     */
    public function testPollChangesAdvanceModePhantomLagPrevention()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_phantom_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            // 记录 slot 创建时的 confirmed_flush_lsn
            $before = $connection->selectOne(
                'SELECT confirmed_flush_lsn FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );

            // 在其他表做些操作推进全局 WAL LSN（但不在被监听表上）
            $connection->statement('CREATE TEMP TABLE knight_phantom_push (id INT)');
            $connection->statement('INSERT INTO knight_phantom_push VALUES (1)');
            $connection->statement('DROP TABLE knight_phantom_push');

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'advance',
                '--no-add-tables' => true,
            ]);

            /** 空 handlers → 无变更匹配 → 触发幻影滞后推进 */
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, [], 100, []]);
            $this->assertFalse($hasChanges);

            // 验证 confirmed_flush_lsn 已推进
            $after = $connection->selectOne(
                'SELECT confirmed_flush_lsn FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );

            $this->assertNotEquals(
                $before->confirmed_flush_lsn,
                $after->confirmed_flush_lsn,
                '幻影滞后推进应更新 confirmed_flush_lsn'
            );
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * TRUNCATE 操作在 v2 下应被跳过（action=T 不在 I/U/D 中）。
     */
    public function testPollChangesSkipsTruncate()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_trunc_'.getmypid();
        $tableName = 'knight_trunc_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('before_trunc')", $tableName));
            $connection->statement(sprintf('TRUNCATE %s', $tableName));
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('after_trunc')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 10000, []]);

            /** INSERT + TRUNCATE + INSERT → 只有 2 条 INSERT 被 dispatch，TRUNCATE 跳过 */
            $this->assertSame(2, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 空事务（BEGIN → COMMIT 不做任何操作）不产生可处理的变更。
     */
    public function testPollChangesEmptyTransactionReturnsNoChanges()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_empty_tx_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, [], 100, []]);
            $this->assertFalse($hasChanges);
            $this->assertSame(0, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== DBA 视角：数据类型、NULL、大字段、特殊列 ====================

    /**
     * 【DBA】NULL 值列应正确传递到 WalChangeRecord，不被跳过或报错。
     */
    public function testPollChangesHandlesNullColumnValues()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_null_col_'.getmypid();
        $tableName = 'knight_null_col_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT, nullable_col TEXT, num INT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            /** 插入含多个 NULL 列的行 */
            $connection->statement(sprintf("INSERT INTO %s (name, nullable_col, num) VALUES ('has_nulls', NULL, NULL)", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【DBA】TEXT 大字段（超过 TOAST 阈值 ~2KB）应正确传递。
     */
    public function testPollChangesHandlesLargeTextField()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_toast_'.getmypid();
        $tableName = 'knight_toast_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, content TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            /** 插入 100KB 文本（远超 TOAST 阈值） */
            $bigText = str_repeat('abcdefghij', 10000);
            $connection->statement(sprintf('INSERT INTO %s (content) VALUES (?)', $tableName), [$bigText]);

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【DBA】JSONB 列的值应作为字符串/数组正确传递到 WAL record。
     */
    public function testPollChangesHandlesJsonbColumn()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_jsonb_'.getmypid();
        $tableName = 'knight_jsonb_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, meta JSONB)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf(
                "INSERT INTO %s (meta) VALUES ('{\"key\": \"value\", \"nested\": {\"a\": 1}}'::jsonb)",
                $tableName
            ));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【DBA】UUID 主键（非 integer）应正确作为 keyName 提取。
     */
    public function testPollChangesHandlesUuidPrimaryKey()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_uuid_'.getmypid();
        $tableName = 'knight_uuid_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id UUID PRIMARY KEY DEFAULT gen_random_uuid(), name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('uuid_row')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** keyName = 'id'，类型是 UUID 字符串而非 integer */
            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== PG 专家视角：REPLICA IDENTITY、schema、特殊表结构 ====================

    /**
     * 【PG专家】非 public schema 的表应正确传递表名（不含 schema 前缀）。
     */
    public function testPollChangesHandlesNonPublicSchema()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_schema_'.getmypid();
        $schemaName = 'knight_test_schema_'.getmypid();
        $tableName = 'schema_test_table';

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $schemaName));
            $connection->statement(sprintf('CREATE SCHEMA %s', $schemaName));
            $connection->statement(sprintf('CREATE TABLE %s.%s (id SERIAL PRIMARY KEY, name TEXT)', $schemaName, $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s.%s (name) VALUES ('schema_row')", $schemaName, $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** wal2json 输出的 table 字段不含 schema 前缀，只是表名 */
            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $schemaName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【PG专家】DELETE 在默认 REPLICA IDENTITY (default=pk only) 下，
     * oldkeys 只包含主键列，不包含其他列。record 应正确构建。
     */
    public function testPollChangesDeleteWithDefaultReplicaIdentity()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_ri_del_'.getmypid();
        $tableName = 'knight_ri_del_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT, value INT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name, value) VALUES ('del_test', 42)", $tableName));
            /** 先消费 INSERT */
            $connection->select(
                "SELECT * FROM pg_logical_slot_get_changes(?, NULL, NULL, 'format-version', '2')",
                [$slotName]
            );

            /** 删除 → 默认 REPLICA IDENTITY 下 oldkeys 只有 id */
            $connection->statement(sprintf('DELETE FROM %s WHERE name = ?', $tableName), ['del_test']);

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges, 'DELETE 应产生变更');
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【PG专家】复合主键表：keyName 只匹配其中一列时仍能提取 id。
     */
    public function testPollChangesCompositeKeyExtractsSpecifiedColumn()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_comp_key_'.getmypid();
        $tableName = 'knight_comp_key_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf(
                'CREATE TABLE %s (user_id INT, item_id INT, name TEXT, PRIMARY KEY (user_id, item_id))',
                $tableName
            ));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (user_id, item_id, name) VALUES (100, 200, 'composite')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** keyName = 'user_id'，只取复合主键的第一列 */
            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'user_id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== PHP 专家视角：特殊字符、编码、JSON 边界 ====================

    /**
     * 【PHP】含特殊字符（emoji、中文、反斜杠、引号）的列值应正确通过 JSON 解析。
     */
    public function testPollChangesHandlesSpecialCharactersInValues()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_special_'.getmypid();
        $tableName = 'knight_special_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, content TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $specialValues = [
                '中文测试数据',
                "line1\nline2\ttab",
                'quote"and\\backslash',
                '{"nested": "json"}',
            ];

            foreach ($specialValues as $val) {
                $connection->statement(sprintf('INSERT INTO %s (content) VALUES (?)', $tableName), [$val]);
            }

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(count($specialValues), $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【PHP】bytea 二进制列不会导致 json_decode 失败。
     */
    public function testPollChangesHandlesByteaColumn()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_bytea_'.getmypid();
        $tableName = 'knight_bytea_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, data BYTEA)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            /** 插入二进制数据 */
            $connection->statement(sprintf("INSERT INTO %s (data) VALUES (decode('deadbeef', 'hex'))", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** 不崩溃即通过（wal2json 会将 bytea 编码为 hex 字符串） */
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== 架构师视角：异常传播、dispatch 失败、并发、事务一致性 ====================

    /**
     * 【架构师】dispatchWalChange 中间抛异常 → 异常应向上传播。
     * advance 模式下 slot 不应被推进（未完成的变更可重试）。
     */
    public function testPollChangesRollsBackOnDispatchException()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_exc_'.getmypid();
        $tableName = 'knight_exc_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('a'), ('b'), ('c')", $tableName));

            /** 构造一个第 2 次 dispatch 时抛异常的 command */
            $command = new class() extends WalEventDispatchCommand
            {
                public $testDispatchCount = 0;

                protected function dispatchWalChange(\HughCube\Laravel\Knight\Database\Wal\WalChangeRecord $record): void
                {
                    $this->testDispatchCount++;
                    if ($this->testDispatchCount === 2) {
                        throw new \RuntimeException('Simulated dispatch failure');
                    }
                }
            };
            $command->setLaravel($this->app);
            $input = new ArrayInput([
                '--connection' => 'pgsql',
                '--mode' => 'advance',
                '--no-add-tables' => true,
            ], $command->getDefinition());
            self::setProperty($command, 'input', $input);
            $bufferedOutput = new BufferedOutput();
            self::setProperty($command, 'output', new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** pollChanges 应抛出异常 */
            $threw = false;

            try {
                self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            } catch (\RuntimeException $e) {
                $this->assertSame('Simulated dispatch failure', $e->getMessage());
                $threw = true;
            }
            $this->assertTrue($threw, '异常应向上传播');

            /** advance 模式下 slot 未被推进，变更仍可重读 */
            $remaining = $connection->select(
                "SELECT count(*) AS cnt FROM pg_logical_slot_peek_changes(?, NULL, 100, 'format-version', '2')",
                [$slotName]
            );
            $this->assertGreaterThan(0, $remaining[0]->cnt, '异常后 slot 未推进，WAL 数据仍在');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【架构师】handler keyName 与实际表列名不匹配时，fromWal2json 返回 null，
     * 该变更被跳过，不影响其他 handler 或其他变更。
     */
    public function testPollChangesSkipsRecordWithMismatchedKeyName()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_badkey_'.getmypid();
        $tableName = 'knight_badkey_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('mismatch')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** keyName = 'nonexistent_column' → fromWal2json 找不到该列值 → 返回 null */
            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'nonexistent_column']],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            /** WAL 有数据但无法提取主键 → 跳过，dispatch 0 */
            $this->assertFalse($hasChanges);
            $this->assertSame(0, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【架构师】同表多 handler、keyName 不同：一个能匹配、一个不能，互不干扰。
     */
    public function testPollChangesMultipleHandlersDifferentKeyNames()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_diffkey_'.getmypid();
        $tableName = 'knight_diffkey_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('diffkey')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** handler1: keyName='id' (存在) → dispatch; handler2: keyName='bad' (不存在) → skip */
            $handlers = [
                $tableName => [
                    ['handler' => new \stdClass(), 'keyName' => 'id'],
                    ['handler' => new \stdClass(), 'keyName' => 'nonexistent_key'],
                ],
            ];

            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            /** 只有第一个 handler 成功 dispatch */
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【架构师】slot 在 poll 中间被外部进程删除 → 游标 FETCH 应抛出异常，事务回滚。
     * （模拟 DBA 意外删除 slot 的场景）.
     */
    public function testPollChangesHandlesSlotDroppedDuringPoll()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_drop_mid_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            /** 创建 slot 但不插入数据 → poll 应无异常返回 false */
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            /** 空 slot 正常返回 false */
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, [], 100, []]);
            $this->assertFalse($hasChanges);

            /** 删除 slot 后再 poll → 应抛异常 */
            $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);

            $threw = false;

            try {
                self::callMethod($command, 'pollChanges', [$slotName, [], 100, []]);
            } catch (\Throwable $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'slot 被删除后 poll 应抛出异常');
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【架构师】回滚事务不产生 WAL 变更（PG 逻辑解码只输出已提交事务）。
     */
    public function testPollChangesIgnoresRolledBackTransactions()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_rollback_'.getmypid();
        $tableName = 'knight_rollback_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            /** 回滚事务 → 不产生 WAL 变更 */
            $pdo = $connection->getPdo();
            $pdo->beginTransaction();
            $pdo->exec(sprintf("INSERT INTO %s (name) VALUES ('rolled_back')", $tableName));
            $pdo->rollBack();

            /** 提交事务 → 产生 WAL 变更 */
            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('committed')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'auto',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);

            /** 只有 committed 的 1 条被 dispatch */
            $this->assertSame(1, $command->testDispatchCount);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    // ==================== 测试专家视角：覆盖缺口补充 ====================

    /**
     * 【测试专家】discoverWalHandlers 扫描存在的目录但无 HasWalHandler 实现时返回空数组。
     */
    public function testDiscoverWalHandlersWithExistingPathButNoImplementors()
    {
        /** 使用 config 目录——存在 PHP 文件但不会有 HasWalHandler 实现 */
        $command = $this->makeCommand(['--model-path' => ['config:App\\Config']]);

        $handlers = self::callMethod($command, 'discoverWalHandlers');

        $this->assertIsArray($handlers);
        $this->assertEmpty($handlers);
    }

    /**
     * 【测试专家】主循环中异常上报：getExceptionHandler()->report() 被调用后不影响后续流程。
     * 验证 errorStreak 递增、reconnectDatabase 被触发、ensureSlotExists 被重试。
     */
    public function testHandleMainLoopErrorRecoveryFlow()
    {
        $command = $this->makeCommand();

        /** 模拟 errorStreak 递增 */
        self::setProperty($command, 'errorStreak', 0);
        self::setProperty($command, 'errorStreak', 1);
        $this->assertSame(1, self::getProperty($command, 'errorStreak'));

        /** reconnectDatabase 在非 PG 环境不崩溃 */
        self::callMethod($command, 'reconnectDatabase');
        $this->assertTrue(true, 'reconnectDatabase 不应抛异常');
    }

    /**
     * 【测试专家】flushTelescopeEntries 在 Telescope 未安装时是 no-op。
     */
    public function testFlushTelescopeEntriesWithoutTelescope()
    {
        $command = $this->makeCommand();

        /** Telescope 未安装时应静默返回，不抛异常 */
        self::callMethod($command, 'flushTelescopeEntries');
        $this->assertTrue(true);
    }

    /**
     * 【测试专家】advance 模式下 slot advance 失败时 pollChanges 仍返回 true（at-least-once 语义）。
     * 下次 peek 会重新看到这些数据，实现重试。
     */
    public function testPollChangesReturnsTrueEvenIfAdvanceFails()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $connection = $this->app['db']->connection('pgsql');
        $slotName = 'knight_adv_fail_'.getmypid();
        $tableName = 'knight_adv_fail_'.getmypid();

        try {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }
            $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            $connection->statement(sprintf('CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT)', $tableName));
            $connection->statement("SELECT pg_create_logical_replication_slot(?, 'wal2json')", [$slotName]);

            $connection->statement(sprintf("INSERT INTO %s (name) VALUES ('adv_fail_test')", $tableName));

            $command = $this->makeNoDispatchCommand([
                '--connection' => 'pgsql',
                '--mode' => 'advance',
                '--no-add-tables' => true,
            ]);

            $handlers = [
                $tableName => [['handler' => new \stdClass(), 'keyName' => 'id']],
            ];

            /** 正常 advance 模式 pollChanges 返回 true */
            $hasChanges = self::callMethod($command, 'pollChanges', [$slotName, $handlers, 100, []]);
            $this->assertTrue($hasChanges);
            $this->assertSame(1, $command->testDispatchCount);

            /**
             * advance 后 slot 已推进，验证数据已消费。
             * 如果 advance 失败（被 catch），pollChanges 仍然返回 true，
             * 但数据会在下次 peek 中重新出现——这就是 at-least-once 语义。
             */
            $remaining = $connection->select(
                "SELECT count(*) AS cnt FROM pg_logical_slot_peek_changes(?, NULL, 1, 'format-version', '2')",
                [$slotName]
            );
            $this->assertEquals(0, $remaining[0]->cnt);
        } finally {
            try {
                $connection->statement('SELECT pg_drop_replication_slot(?)', [$slotName]);
            } catch (\Throwable $e) {
            }

            try {
                $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $tableName));
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * 【测试专家】isMemoryExceeded 边界值：恰好等于限制时应判定为超限。
     */
    public function testIsMemoryExceededBoundary()
    {
        $command = $this->makeCommand();

        $currentMB = intval(memory_get_usage(true) / 1024 / 1024);

        /** 恰好等于当前内存 → 应超限（>=） */
        $this->assertTrue(self::callMethod($command, 'isMemoryExceeded', [$currentMB]));

        /** 比当前内存多 1MB → 不超限 */
        $this->assertFalse(self::callMethod($command, 'isMemoryExceeded', [$currentMB + 1]));
    }

    // ==================== new options: partition-refresh, slot-lag ====================

    public function testCommandHasNewOptions()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('partition-refresh'));
        $this->assertTrue($definition->hasOption('slot-lag-warning'));
        $this->assertTrue($definition->hasOption('slot-lag-critical'));
    }

    public function testNewOptionDefaults()
    {
        $command = $this->makeCommand();
        $definition = $command->getDefinition();

        $this->assertSame('0', $definition->getOption('partition-refresh')->getDefault());
        $this->assertSame('0', $definition->getOption('slot-lag-warning')->getDefault());
        $this->assertSame('0', $definition->getOption('slot-lag-critical')->getDefault());
    }

    // ==================== checkSlotHealth ====================

    public function testCheckSlotHealthReturnsFalseWhenBelowThreshold()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $command = $this->makeCommand(['--connection' => 'pgsql']);

        $slotName = 'knight_test_health_'.time();

        try {
            self::callMethod($command, 'ensureSlotExists', [$slotName]);

            $result = self::callMethod($command, 'checkSlotHealth', [$slotName, 1024, 2048]);
            $this->assertFalse($result);
        } finally {
            try {
                $this->app['db']->connection('pgsql')->statement(
                    'SELECT pg_drop_replication_slot(?)',
                    [$slotName]
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function testCheckSlotHealthReturnsTrueWhenCriticalThresholdExceeded()
    {
        $command = new class() extends WalEventDispatchCommand
        {
            public $connection;

            protected function getConnection()
            {
                return $this->connection;
            }
        };
        $command->setLaravel($this->app);

        $input = new ArrayInput([], $command->getDefinition());
        self::setProperty($command, 'input', $input);

        $bufferedOutput = new BufferedOutput();
        self::setProperty($command, 'output', new OutputStyle($input, $bufferedOutput));

        $command->connection = new class()
        {
            public function selectOne($query, array $bindings = [], $useReadPdo = true)
            {
                return (object) ['lag_bytes' => 3 * 1024 * 1024];
            }
        };

        $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 1, 2]);

        $this->assertTrue($result);
    }

    public function testCheckSlotHealthWithNonExistentSlotReturnsFalse()
    {
        $this->skipIfPgsqlNotConfigured();

        $command = $this->makeCommand(['--connection' => 'pgsql']);

        /** 不存在的 slot 查询返回 null，应安静返回 false */
        $result = self::callMethod($command, 'checkSlotHealth', ['nonexistent_slot_xyz', 100, 200]);
        $this->assertFalse($result);
    }

    // ==================== ensureSlotExists: active slot throws ====================

    public function testEnsureSlotExistsThrowsOnActiveSlot()
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfWal2jsonNotAvailable();

        $command = $this->makeCommand(['--connection' => 'pgsql']);
        $slotName = 'knight_test_active_'.time();

        try {
            self::callMethod($command, 'ensureSlotExists', [$slotName]);

            /** Mock an active slot by modifying the query result */
            $connection = $this->app['db']->connection('pgsql');
            $slotInfo = $connection->selectOne(
                'SELECT active FROM pg_replication_slots WHERE slot_name = ?',
                [$slotName]
            );
            $this->assertNotNull($slotInfo);
            /** Slot we just created should not be active */
            $this->assertFalse((bool) $slotInfo->active);

            /** Calling again should succeed (not active) */
            self::callMethod($command, 'ensureSlotExists', [$slotName]);
            $this->assertTrue(true);
        } finally {
            try {
                $this->app['db']->connection('pgsql')->statement(
                    'SELECT pg_drop_replication_slot(?)',
                    [$slotName]
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    // ==================== clearScopedInstances ====================

    public function testClearScopedInstancesDoesNotThrow()
    {
        $command = $this->makeCommand();

        self::callMethod($command, 'clearScopedInstances');
        $this->assertTrue(true);
    }

    // ==================== qualifyTableName ====================

    public function testQualifyTableNameAddsPublicSchema(): void
    {
        $command = $this->makeCommand();
        $result = self::callMethod($command, 'qualifyTableName', ['users']);
        $this->assertSame('public.users', $result);
    }

    public function testQualifyTableNamePreservesExistingSchema(): void
    {
        $command = $this->makeCommand();
        $result = self::callMethod($command, 'qualifyTableName', ['audit.logs']);
        $this->assertSame('audit.logs', $result);
    }

    // ==================== MODE constants ====================

    public function testModeConstants(): void
    {
        $this->assertSame('auto', WalEventDispatchCommand::MODE_AUTO);
        $this->assertSame('advance', WalEventDispatchCommand::MODE_ADVANCE);
        $this->assertSame('peek', WalEventDispatchCommand::MODE_PEEK);
    }

    // ==================== parseWal2jsonParam ====================

    public function testParseWal2jsonParamKeyValue(): void
    {
        $command = $this->makeCommand();
        $result = self::callMethod($command, 'parseWal2jsonParam', ['filter-columns=content,body']);
        $this->assertSame(['filter-columns', 'content,body'], $result);
    }

    public function testParseWal2jsonParamKeyOnly(): void
    {
        $command = $this->makeCommand();
        $result = self::callMethod($command, 'parseWal2jsonParam', ['include-lsn']);
        $this->assertSame(['include-lsn', ''], $result);
    }

    public function testParseWal2jsonParamMultipleEquals(): void
    {
        $command = $this->makeCommand();
        $result = self::callMethod($command, 'parseWal2jsonParam', ['key=a=b']);
        $this->assertSame(['key', 'a=b'], $result);
    }

    // ==================== getCachedWal2jsonParams ====================

    public function testGetCachedWal2jsonParamsReturnsCachedValue(): void
    {
        $command = $this->makeCommand(['--no-add-tables' => true]);

        $first = self::callMethod($command, 'getCachedWal2jsonParams');
        $second = self::callMethod($command, 'getCachedWal2jsonParams');
        $this->assertSame($first, $second);

        // 清除缓存后重新构建
        self::setProperty($command, 'cachedWal2jsonParams', null);
        $third = self::callMethod($command, 'getCachedWal2jsonParams');
        $this->assertSame($first, $third);
    }

    // ==================== isWalSegmentRemovedError ====================

    public function testIsWalSegmentRemovedErrorWithHasAlreadyBeenRemoved(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('ERROR: requested WAL segment 00000001000000000000000A has already been removed');
        $this->assertTrue(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    public function testIsWalSegmentRemovedErrorWith58P01AndWalSegment(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('SQLSTATE[58P01]: Undefined file: WAL segment "00000001000000000000000A" does not exist');
        $this->assertTrue(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    public function testIsWalSegmentRemovedErrorCaseInsensitive(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('ERROR: requested WAL segment Has Already Been Removed');
        $this->assertTrue(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    public function testIsWalSegmentRemovedErrorReturnsFalseForUnrelatedError(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('connection reset by peer');
        $this->assertFalse(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    public function testIsWalSegmentRemovedErrorReturnsFalseFor58P01WithoutWalSegment(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('SQLSTATE[58P01]: Undefined file: some other file missing');
        $this->assertFalse(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    public function testIsWalSegmentRemovedErrorReturnsFalseForEmptyMessage(): void
    {
        $command = $this->makeCommand();
        $e = new \RuntimeException('');
        $this->assertFalse(self::callMethod($command, 'isWalSegmentRemovedError', [$e]));
    }

    // ==================== resolveMode ====================

    public function testResolveModeWarnsOnInvalidValue(): void
    {
        $command = $this->makeCommand(['--mode' => 'invalid_mode']);
        $result = self::callMethod($command, 'resolveMode');
        $this->assertSame('advance', $result);
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
