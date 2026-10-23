<?php

namespace HughCube\Laravel\Knight\Console\Commands {
    class WalEventDispatchCommandRuntimeState
    {
        public static bool $mockPcntl = false;
        public static bool $mockSleep = false;
        public static array $sleepCalls = [];
        public static array $asyncSignalCalls = [];
        public static array $signalHandlers = [];
        public static array $classExistsOverrides = [];

        public static function reset(): void
        {
            self::$mockPcntl = false;
            self::$mockSleep = false;
            self::$sleepCalls = [];
            self::$asyncSignalCalls = [];
            self::$signalHandlers = [];
            self::$classExistsOverrides = [];
        }
    }

    if (!function_exists(__NAMESPACE__.'\\extension_loaded')) {
        function extension_loaded($name)
        {
            if ($name === 'pcntl' && WalEventDispatchCommandRuntimeState::$mockPcntl) {
                return true;
            }

            return \extension_loaded($name);
        }
    }

    if (!function_exists(__NAMESPACE__.'\\pcntl_async_signals')) {
        function pcntl_async_signals($enable)
        {
            WalEventDispatchCommandRuntimeState::$asyncSignalCalls[] = $enable;

            return true;
        }
    }

    if (!function_exists(__NAMESPACE__.'\\pcntl_signal')) {
        function pcntl_signal($signal, $handler)
        {
            WalEventDispatchCommandRuntimeState::$signalHandlers[$signal] = $handler;

            return true;
        }
    }

    if (!function_exists(__NAMESPACE__.'\\usleep')) {
        function usleep($microseconds)
        {
            if (WalEventDispatchCommandRuntimeState::$mockSleep) {
                WalEventDispatchCommandRuntimeState::$sleepCalls[] = (int) $microseconds;

                return 0;
            }

            return \usleep($microseconds);
        }
    }

    if (!function_exists(__NAMESPACE__.'\\class_exists')) {
        function class_exists($class, $autoload = true)
        {
            if (array_key_exists($class, WalEventDispatchCommandRuntimeState::$classExistsOverrides)) {
                return WalEventDispatchCommandRuntimeState::$classExistsOverrides[$class];
            }

            return \class_exists($class, $autoload);
        }
    }
}

namespace HughCube\Laravel\Knight\Tests\Console\Commands {
    use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommand;
    use HughCube\Laravel\Knight\Console\Commands\WalEventDispatchCommandRuntimeState;
    use HughCube\Laravel\Knight\Contracts\Database\HasWalHandler;
    use HughCube\Laravel\Knight\Jobs\WalChangesDispatchJob;
    use HughCube\Laravel\Knight\Tests\TestCase;
    use Illuminate\Console\OutputStyle;
    use Illuminate\Contracts\Debug\ExceptionHandler;
    use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
    use Illuminate\Support\Facades\Queue;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\BufferedOutput;

    class WalEventDispatchCommandBehaviorTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            WalEventDispatchCommandRuntimeState::reset();
        }

        protected function tearDown(): void
        {
            WalEventDispatchCommandRuntimeState::reset();
            parent::tearDown();
        }

        public function testHandleRunsSingleIterationAndStops(): void
        {
            config(['app.name' => 'TestApp', 'app.env' => 'testing']);

            $command = new TestableWalEventDispatchCommand();
            $command->handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                    ['handler' => new DummyWalHandlerNoKey(), 'keyName' => 'id'],
                ],
            ];
            $command->partitionMap = ['users_2024' => 'users'];
            $command->pollReturn = false;
            $command->exceptionHandler = $this->createMock(ExceptionHandler::class);

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.25',
                '--batch'    => '5',
            ]);

            $command->handle();

            $this->assertSame('ensureSlotExists', $command->calls[0][0]);
            $this->assertSame('testapp_testing_wal_event', $command->calls[0][1]);
            $this->assertSame('pollChanges', $command->calls[1][0]);
            $this->assertFalse($command->reconnectCalled);
            $this->assertContains(250000, WalEventDispatchCommandRuntimeState::$sleepCalls);
        }

        public function testHandlePollErrorReportsAndReconnectsWithBackoff(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $command = new TestableWalEventDispatchCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('poll failed');
            $command->exceptionHandler = $exceptionHandler;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
            ]);

            $command->handle();

            $this->assertTrue($command->reconnectCalled);
            $this->assertContains(1000000, WalEventDispatchCommandRuntimeState::$sleepCalls);
            $this->assertSame(1, self::getProperty($command, 'errorStreak'));

            $ensureSlotCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'ensureSlotExists';
            });
            $this->assertCount(2, $ensureSlotCalls, 'ensureSlotExists should be called at startup and in error recovery');
        }

        public function testHandlePollErrorEnsureSlotExistsFailureDoesNotCrash(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $command = new TestableWalEnsureSlotFailsCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('slot deleted');
            $command->exceptionHandler = $exceptionHandler;
            $command->ensureSlotException = new \RuntimeException('create slot failed');

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
            ]);

            $command->handle();

            $this->assertTrue($command->reconnectCalled);
            $this->assertContains(1000000, WalEventDispatchCommandRuntimeState::$sleepCalls);
            $this->assertSame(1, self::getProperty($command, 'errorStreak'));
            $this->assertTrue($command->ensureSlotCalledInErrorPath);
        }

        public function testHandleExitsWhenConsecutiveErrorsReachMaxErrors(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);

            $command = new TestableWalMultiErrorCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('persistent failure');
            $command->exceptionHandler = $exceptionHandler;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'   => '0.1',
                '--batch'      => '10',
                '--max-errors' => '3',
            ]);

            $command->handle();

            $this->assertSame(3, self::getProperty($command, 'errorStreak'));

            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(3, $pollCalls);
        }

        public function testHandleContinuesWhenErrorsRecoverBeforeMaxErrors(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);

            $command = new TestableWalMultiErrorCommand();
            $command->handlers = [];
            $command->failCount = 2;
            $command->pollThrowable = new \RuntimeException('transient failure');
            $command->exceptionHandler = $exceptionHandler;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'   => '0.1',
                '--batch'      => '10',
                '--max-errors' => '5',
            ]);

            $command->handle();

            $this->assertSame(0, self::getProperty($command, 'errorStreak'));

            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(3, $pollCalls);
        }

        public function testHandleUnlimitedErrorsWhenMaxErrorsIsZero(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);

            $command = new TestableWalMultiErrorCommand();
            $command->handlers = [];
            $command->failCount = 5;
            $command->pollThrowable = new \RuntimeException('failure');
            $command->exceptionHandler = $exceptionHandler;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'   => '0.1',
                '--batch'      => '10',
                '--max-errors' => '0',
            ]);

            $command->handle();

            $this->assertSame(0, self::getProperty($command, 'errorStreak'));

            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(6, $pollCalls);
        }

        public function testDiscoverWalHandlersScansValidAndInvalidClasses(): void
        {
            $relativeDir = 'tests/.temp/wal_scan_'.md5((string) microtime(true));
            $absoluteDir = base_path($relativeDir);
            @mkdir($absoluteDir, 0777, true);

            file_put_contents($absoluteDir.'/NoClass.php', "<?php\nnamespace TestWal;\n// no class\n");
            file_put_contents($absoluteDir.'/PlainClass.php', "<?php\nnamespace TestWal;\nclass PlainClass {}\n");
            file_put_contents(
                $absoluteDir.'/AbstractWal.php',
                <<<'PHP'
<?php
namespace TestWal;
abstract class AbstractWal implements \HughCube\Laravel\Knight\Contracts\Database\HasWalHandler
{
    public function getTable() { return 'orders'; }
    public function onKnightModelChanged(): void {}
}
PHP
            );
            file_put_contents(
                $absoluteDir.'/ValidWal.php',
                <<<'PHP'
<?php
namespace TestWal;
class ValidWal implements \HughCube\Laravel\Knight\Contracts\Database\HasWalHandler
{
    public function getTable() { return 'orders'; }
    public function getKeyName() { return 'uuid'; }
    public function onKnightModelChanged(): void {}
}
PHP
            );
            file_put_contents(
                $absoluteDir.'/ValidWalDefaultKey.php',
                <<<'PHP'
<?php
namespace TestWal;
class ValidWalDefaultKey implements \HughCube\Laravel\Knight\Contracts\Database\HasWalHandler
{
    public function getTable() { return 'orders'; }
    public function onKnightModelChanged(): void {}
}
PHP
            );
            file_put_contents($absoluteDir.'/BrokenReflection.php', "<?php\nnamespace TestWal;\n// intentionally no class\n");
            file_put_contents($absoluteDir.'/README.txt', 'ignore');

            WalEventDispatchCommandRuntimeState::$classExistsOverrides['TestWal\\BrokenReflection'] = true;

            $loader = function ($class) use ($absoluteDir) {
                $prefix = 'TestWal\\';
                if (strpos($class, $prefix) !== 0) {
                    return;
                }

                $relative = substr($class, strlen($prefix));
                $path = $absoluteDir.'/'.str_replace('\\', '/', $relative).'.php';
                if (is_file($path)) {
                    require_once $path;
                }
            };
            spl_autoload_register($loader);

            try {
                $command = new WalEventDispatchCommand();
                $this->initializeCommand($command, [
                    '--model-path' => [$relativeDir.':TestWal'],
                ]);

                $handlers = self::callMethod($command, 'discoverWalHandlers');

                $this->assertArrayHasKey('orders', $handlers);
                $this->assertCount(2, $handlers['orders']);

                $keyNames = array_values(array_map(static function ($meta) {
                    return $meta['keyName'];
                }, $handlers['orders']));
                sort($keyNames);
                $this->assertSame(['id', 'uuid'], $keyNames);
            } finally {
                spl_autoload_unregister($loader);
                $this->deleteDirectory($absoluteDir);
            }
        }

        public function testBuildPartitionMapMapsReturnedRows(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };
            $command->connection = new class() {
                public function select($query, array $bindings = [])
                {
                    return [
                        (object) ['child_table' => 'orders_2024_01', 'parent_table' => 'orders'],
                        (object) ['child_table' => 'orders_2024_02', 'parent_table' => 'orders'],
                    ];
                }
            };

            $partitionMap = self::callMethod($command, 'buildPartitionMap');

            $this->assertSame([
                'orders_2024_01' => 'orders',
                'orders_2024_02' => 'orders',
            ], $partitionMap);
        }

        public function testGetConnectionForcesWriteConnectionOnSelectedConnection(): void
        {
            $originalDb = app('db');

            $connection = new class() {
                public $writeConnectionForced = false;

                public function useWriteConnectionWhenReading($value = true)
                {
                    $this->writeConnectionForced = $value;

                    return $this;
                }
            };

            $db = new class($connection) {
                public $requestedConnection = null;
                private $connection;

                public function __construct($connection)
                {
                    $this->connection = $connection;
                }

                public function connection($name = null)
                {
                    $this->requestedConnection = $name;

                    return $this->connection;
                }
            };

            app()->instance('db', $db);

            try {
                $command = new class() extends WalEventDispatchCommand {
                    public function exposedGetConnection()
                    {
                        return $this->getConnection();
                    }
                };

                $this->initializeCommand($command, ['--connection' => 'primary-test']);

                $result = $command->exposedGetConnection();

                $this->assertSame($connection, $result);
                $this->assertSame('primary-test', $db->requestedConnection);
                $this->assertTrue($connection->writeConnectionForced);
            } finally {
                app()->instance('db', $originalDb);
            }
        }

        public function testPollChangesSkipsInvalidRowsAndAdvancesSlot(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/1', 'data' => '{invalid-json'],
                (object) ['lsn' => '0/2', 'data' => json_encode(['change' => []])],
                (object) ['lsn' => '0/3', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'columnnames' => ['id'], 'columnvalues' => [1]],
                ]])],
                (object) ['lsn' => '0/4', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'table' => 'users', 'columnnames' => ['name'], 'columnvalues' => ['alice']],
                ]])],
                (object) ['lsn' => '0/5', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [1]],
                ]])],
                (object) ['lsn' => '0/6', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [1]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'advance', '--format-version' => '1']);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_test', $handlers, 1000, []]);

            $this->assertTrue($result);
            $this->assertCount(1, $connection->cursorCalls);
            $this->assertFalse($connection->cursorCalls[0]['useReadPdo']);
            $this->assertSame([\PDO::FETCH_OBJ], $connection->cursorCalls[0]['fetchUsing']);
            $this->assertCount(1, $connection->statements);
            $this->assertStringContainsString('pg_replication_slot_advance', $connection->statements[0]['query']);
            $this->assertSame(['slot_test', '0/6'], $connection->statements[0]['bindings']);

            // Two WAL entries for id=1 (no dedup), skip rows without table/id
            Queue::assertPushed(WalChangesDispatchJob::class, 2);
            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->getRecord()->getId() == 1;
            });
        }

        public function testPollChangesInAutoModeAdvancesSlotAfterSuccessfulDispatch(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/A', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [10]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'auto', '--format-version' => '1']);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_test_auto', $handlers, 1000, []]);

            $this->assertTrue($result);
            /** auto mode 在成功分发后也会 advance，保留“成功后消费”的外部语义 */
            $this->assertCount(1, $connection->statements);
            $this->assertStringContainsString('pg_replication_slot_advance', $connection->statements[0]['query']);
            $this->assertSame(['slot_test_auto', '0/A'], $connection->statements[0]['bindings']);

            Queue::assertPushed(WalChangesDispatchJob::class, 1);
            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->getRecord()->getId() == 10;
            });
        }

        public function testPollChangesUsesEffectiveFormatVersionFromWal2jsonParams(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/A1', 'data' => json_encode(['change' => [
                    ['kind' => 'update', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [13]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, [
                '--mode'            => 'advance',
                '--wal2json-params' => ['format-version=1'],
            ]);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_test_format_override', $handlers, 1000, []]);

            $this->assertTrue($result);
            $this->assertCount(1, $connection->cursorCalls);
            $this->assertSame('format-version', $connection->cursorCalls[0]['bindings'][2]);
            $this->assertSame('1', $connection->cursorCalls[0]['bindings'][3]);
            Queue::assertPushed(WalChangesDispatchJob::class, 1);
            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->getRecord()->getId() == 13;
            });
        }

        public function testPollChangesInPeekModeDoesNotAdvanceSlot(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/B', 'data' => json_encode(['change' => [
                    ['kind' => 'insert', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [11]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'peek', '--format-version' => '1']);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_test_peek', $handlers, 1000, []]);

            $this->assertTrue($result);
            $this->assertEmpty($connection->statements);

            Queue::assertPushed(WalChangesDispatchJob::class, 1);
            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->getRecord()->getId() == 11;
            });
        }

        public function testPollChangesAdvanceModeIgnoresSlotAdvanceFailure(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/C', 'data' => json_encode(['change' => [
                    ['kind' => 'insert', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [12]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);
            $connection->advanceThrows = true;

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'advance', '--format-version' => '1']);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_test_advance', $handlers, 1000, []]);

            $this->assertTrue($result);
            $this->assertCount(1, $connection->statements);

            Queue::assertPushed(WalChangesDispatchJob::class, 1);
            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->getRecord()->getId() == 12;
            });
        }

        public function testHandleStopsImmediatelyOnErrorClass(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $command = new TestableWalEventDispatchCommand();
            $command->handlers = [];
            /** TypeError extends \Error，应立即停止不重试 */
            $command->pollThrowable = new \TypeError('type error in code');
            $command->exceptionHandler = $exceptionHandler;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'   => '0.1',
                '--batch'      => '10',
                '--max-errors' => '10',
            ]);

            $command->handle();

            /** Error 不应触发指数退避重试，errorStreak 应为 0（Error 路径不增加） */
            $this->assertSame(0, self::getProperty($command, 'errorStreak'));

            /** 只应有 1 次 pollChanges 调用（立即退出，不重试） */
            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(1, $pollCalls);

            /** 不应触发 reconnect */
            $this->assertFalse($command->reconnectCalled);

            /** 不应有退避 sleep */
            $this->assertEmpty(WalEventDispatchCommandRuntimeState::$sleepCalls);
        }

        public function testRegisterSignalHandlersUsesPcntlWhenAvailable(): void
        {
            if (!defined('SIGTERM')) {
                define('SIGTERM', 15);
            }
            if (!defined('SIGINT')) {
                define('SIGINT', 2);
            }

            WalEventDispatchCommandRuntimeState::$mockPcntl = true;

            $command = new WalEventDispatchCommand();
            $this->initializeCommand($command);

            self::setProperty($command, 'shouldRun', true);
            self::callMethod($command, 'registerSignalHandlers');

            $this->assertNotEmpty(WalEventDispatchCommandRuntimeState::$asyncSignalCalls);
            $this->assertArrayHasKey(SIGTERM, WalEventDispatchCommandRuntimeState::$signalHandlers);
            $this->assertArrayHasKey(SIGINT, WalEventDispatchCommandRuntimeState::$signalHandlers);

            $handler = WalEventDispatchCommandRuntimeState::$signalHandlers[SIGTERM];
            $this->assertIsCallable($handler);
            $handler();

            $this->assertFalse(self::getProperty($command, 'shouldRun'));

            self::setProperty($command, 'shouldRun', true);
            $interruptHandler = WalEventDispatchCommandRuntimeState::$signalHandlers[SIGINT];
            $this->assertIsCallable($interruptHandler);
            $interruptHandler();

            $this->assertFalse(self::getProperty($command, 'shouldRun'));
        }

        public function testReconnectDatabaseHandlesReconnectFailure(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                protected function getConnection()
                {
                    return new class() {
                        public function reconnect(): void
                        {
                            throw new \RuntimeException('reconnect failed');
                        }
                    };
                }
            };

            $this->initializeCommand($command);

            self::callMethod($command, 'reconnectDatabase');
            $this->assertTrue(true);
        }

        // ==================== checkSlotHealth (mock connection) ====================

        public function testCheckSlotHealthReturnsFalseWhenNoLag(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return (object) ['lag_bytes' => 0];
                }
            };

            $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 100, 200]);
            $this->assertFalse($result);
        }

        public function testCheckSlotHealthWarnsOnWarningThreshold(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return (object) ['lag_bytes' => 150 * 1024 * 1024];
                }
            };

            $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 100, 200]);
            $this->assertFalse($result);
        }

        public function testCheckSlotHealthReturnsTrueOnCriticalThreshold(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return (object) ['lag_bytes' => 250 * 1024 * 1024];
                }
            };

            $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 100, 200]);
            $this->assertTrue($result);
        }

        public function testCheckSlotHealthReturnsFalseOnQueryException(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    throw new \RuntimeException('connection lost');
                }
            };

            $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 100, 200]);
            $this->assertFalse($result);
        }

        public function testCheckSlotHealthReturnsFalseWhenSlotNotFound(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return null;
                }
            };

            $result = self::callMethod($command, 'checkSlotHealth', ['slot_test', 100, 200]);
            $this->assertFalse($result);
        }

        // ==================== ensureSlotExists error paths (mock connection) ====================

        public function testEnsureSlotExistsThrowsOnWrongPlugin(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return (object) ['plugin' => 'test_decoding', 'active' => false, 'active_pid' => null];
                }
            };

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('instead of wal2json');

            self::callMethod($command, 'ensureSlotExists', ['slot_test']);
        }

        public function testEnsureSlotExistsThrowsOnActiveSlotMocked(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);

            $command->connection = new class() {
                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    return (object) ['plugin' => 'wal2json', 'active' => true, 'active_pid' => 12345];
                }
            };

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('currently active');

            self::callMethod($command, 'ensureSlotExists', ['slot_test']);
        }

        // ==================== pollChanges v2 format (mock connection) ====================

        public function testPollChangesWithFormatVersion2(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/10', 'data' => json_encode([
                    'action'  => 'I',
                    'table'   => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'integer', 'value' => 1],
                        ['name' => 'name', 'type' => 'text', 'value' => 'alice'],
                    ],
                ])],
                (object) ['lsn' => '0/11', 'data' => json_encode([
                    'action'  => 'U',
                    'table'   => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'integer', 'value' => 1],
                        ['name' => 'name', 'type' => 'text', 'value' => 'bob'],
                    ],
                ])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'advance', '--format-version' => '2']);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            $result = self::callMethod($command, 'pollChanges', ['slot_v2', $handlers, 1000, []]);

            $this->assertTrue($result);
            Queue::assertPushed(WalChangesDispatchJob::class, 2);
        }

        // ==================== dispatchWalChange: queue connection/queue options ====================

        public function testDispatchWalChangeSetsQueueConnectionAndQueue(): void
        {
            Queue::fake();

            $rows = [
                (object) ['lsn' => '0/Q1', 'data' => json_encode(['change' => [
                    ['kind' => 'insert', 'table' => 'users', 'columnnames' => ['id'], 'columnvalues' => [99]],
                ]])],
            ];

            $connection = new MockCursorConnection($rows);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, [
                '--mode'             => 'advance',
                '--format-version'   => '1',
                '--queue-connection' => 'redis',
                '--queue'            => 'wal-events',
            ]);
            $command->connection = $connection;

            $handlers = [
                'users' => [
                    ['handler' => new DummyWalHandler(), 'keyName' => 'id'],
                ],
            ];

            self::callMethod($command, 'pollChanges', ['slot_queue', $handlers, 1000, []]);

            Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
                return $job->connection === 'redis' && $job->queue === 'wal-events';
            });
        }

        // ==================== flushTelescopeEntries: store exception path ====================

        public function testFlushTelescopeEntriesHandlesStoreException(): void
        {
            /** 绑定一个假的 EntriesRepository 以确保 app() 调用不会先于 store() 抛异常 */
            $this->app->bind('Laravel\Telescope\Contracts\EntriesRepository', function () {
                return new \stdClass();
            });

            $command = new class() extends WalEventDispatchCommand {
                protected function getTelescopeClass()
                {
                    return TestFakeTelescope::class;
                }
            };

            $this->initializeCommand($command);

            TestFakeTelescope::$recording = true;
            TestFakeTelescope::$storeCalled = false;
            TestFakeTelescope::$storeThrows = true;

            /** store() 抛异常不应传播，flushTelescopeEntries 应静默处理 */
            self::callMethod($command, 'flushTelescopeEntries');
            $this->assertTrue(TestFakeTelescope::$storeCalled);
        }

        public function testFlushTelescopeEntriesHandlesAppResolveException(): void
        {
            /**
             * 不绑定 EntriesRepository → app() 抛异常。
             * flushTelescopeEntries 的 catch(Throwable) 应静默处理。
             */
            $command = new class() extends WalEventDispatchCommand {
                protected function getTelescopeClass()
                {
                    return TestFakeTelescope::class;
                }
            };

            $this->initializeCommand($command);

            TestFakeTelescope::$recording = true;
            TestFakeTelescope::$storeCalled = false;
            TestFakeTelescope::$storeThrows = false;

            /** 不应抛出异常 */
            self::callMethod($command, 'flushTelescopeEntries');
            /** store() 不会被调用到（app() 先异常） */
            $this->assertFalse(TestFakeTelescope::$storeCalled);
        }

        public function testFlushTelescopeEntriesSkipsWhenNotRecording(): void
        {
            $command = new class() extends WalEventDispatchCommand {
                protected function getTelescopeClass()
                {
                    return TestFakeTelescope::class;
                }
            };

            $this->initializeCommand($command);

            TestFakeTelescope::$recording = false;
            TestFakeTelescope::$storeCalled = false;

            self::callMethod($command, 'flushTelescopeEntries');
            $this->assertFalse(TestFakeTelescope::$storeCalled);
        }

        // ==================== handle: memory exceeded triggers exit ====================

        public function testHandleExitsWhenMemoryExceeded(): void
        {
            $command = new TestableWalMemoryExceededCommand();
            $command->handlers = [
                'users' => [['handler' => new DummyWalHandler(), 'keyName' => 'id']],
            ];
            $command->pollReturn = false;
            $command->exceptionHandler = $this->createMock(ExceptionHandler::class);

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
                '--memory'   => '1',
            ]);

            $command->handle();

            /** 应该只有 1 次 pollChanges 调用后即因内存超限退出 */
            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(1, $pollCalls);
            $this->assertTrue($command->memoryExitTriggered);
        }

        // ==================== handle: partition refresh updates add-tables ====================

        public function testHandleRefreshesPartitionMapOnInterval(): void
        {
            $command = new TestableWalPartitionRefreshCommand();
            $command->handlers = [
                'orders' => [['handler' => new DummyWalHandler(), 'keyName' => 'id']],
            ];
            $command->pollReturn = false;
            $command->exceptionHandler = $this->createMock(ExceptionHandler::class);

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'          => '0.1',
                '--batch'             => '10',
                '--partition-refresh' => '1',
            ]);

            /** 让 partitionMapRefreshedAt 在过去以触发刷新 */
            self::setProperty($command, 'partitionMapRefreshedAt', time() - 10);

            $command->handle();

            $this->assertTrue($command->partitionMapRebuilt, 'Partition map should be rebuilt on refresh interval');
        }

        // ==================== handle: slot-lag-critical triggers exit ====================

        public function testHandleExitsOnSlotLagCritical(): void
        {
            $command = new TestableWalSlotLagCommand();
            $command->handlers = [
                'users' => [['handler' => new DummyWalHandler(), 'keyName' => 'id']],
            ];
            $command->pollReturn = false;
            $command->exceptionHandler = $this->createMock(ExceptionHandler::class);
            $command->slotHealthReturnsTrue = true;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval'          => '0.1',
                '--batch'             => '10',
                '--slot-lag-critical' => '100',
            ]);

            $command->handle();

            /** checkSlotHealth 返回 true 时应立即停止，不调用 pollChanges */
            $pollCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'pollChanges';
            });
            $this->assertCount(0, $pollCalls);
            $this->assertTrue($command->slotHealthChecked);
        }

        // ==================== advanceSlot: extracted method ====================

        public function testAdvanceSlotCallsStatementCorrectly(): void
        {
            $connection = new MockCursorConnection([]);

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);
            $command->connection = $connection;

            self::callMethod($command, 'advanceSlot', [$connection, 'test_slot', '0/FF']);

            $this->assertCount(1, $connection->statements);
            $this->assertStringContainsString('pg_replication_slot_advance', $connection->statements[0]['query']);
            $this->assertSame(['test_slot', '0/FF'], $connection->statements[0]['bindings']);
        }

        public function testAdvanceSlotSilencesException(): void
        {
            $connection = new MockCursorConnection([]);
            $connection->advanceThrows = true;

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command);
            $command->connection = $connection;

            /** 不应抛出异常 */
            self::callMethod($command, 'advanceSlot', [$connection, 'test_slot', '0/FF']);
            $this->assertTrue(true);
        }

        // ==================== pollChanges advance mode: phantom lag advance ====================

        public function testHandleDropsAndRecreatesSlotOnWalSegmentRemoved(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $mockConnection = new MockCursorConnection([]);

            $command = new TestableWalSegmentRemovedCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('ERROR: requested WAL segment 00000001000000000000000A has already been removed');
            $command->exceptionHandler = $exceptionHandler;
            $command->connection = $mockConnection;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
            ]);

            $command->handle();

            /** 验证 drop slot 被调用 */
            $dropCalls = array_filter($mockConnection->statements, static function ($s) {
                return false !== strpos($s['query'], 'pg_drop_replication_slot');
            });
            $this->assertCount(1, $dropCalls, 'pg_drop_replication_slot should be called once');

            /** 验证 ensureSlotExists 在 error path 中被调用（startup + error recovery = 2） */
            $ensureCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'ensureSlotExists';
            });
            $this->assertCount(2, $ensureCalls, 'ensureSlotExists should be called at startup and after drop');
        }

        public function testHandleSkipsEnsureSlotWhenDropFailsOnWalSegmentRemoved(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $mockConnection = new class([]) extends MockCursorConnection {
                public function statement($query, array $bindings = [])
                {
                    $this->statements[] = ['query' => $query, 'bindings' => $bindings];
                    if (false !== strpos($query, 'pg_drop_replication_slot')) {
                        throw new \RuntimeException('slot is active');
                    }

                    return true;
                }
            };

            $command = new TestableWalSegmentRemovedCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('ERROR: requested WAL segment has already been removed');
            $command->exceptionHandler = $exceptionHandler;
            $command->connection = $mockConnection;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
            ]);

            $command->handle();

            /** drop 失败后应跳过 ensureSlotExists，只有 startup 时的 1 次调用 */
            $ensureCalls = array_filter($command->calls, static function ($call) {
                return $call[0] === 'ensureSlotExists';
            });
            $this->assertCount(1, $ensureCalls, 'ensureSlotExists should only be called at startup when drop fails');
        }

        public function testHandleNonWalSegmentErrorDoesNotDropSlot(): void
        {
            $exceptionHandler = $this->createMock(ExceptionHandler::class);
            $exceptionHandler->expects($this->once())->method('report');

            $mockConnection = new MockCursorConnection([]);

            $command = new TestableWalSegmentRemovedCommand();
            $command->handlers = [];
            $command->pollThrowable = new \RuntimeException('connection reset by peer');
            $command->exceptionHandler = $exceptionHandler;
            $command->connection = $mockConnection;

            WalEventDispatchCommandRuntimeState::$mockSleep = true;

            $this->initializeCommand($command, [
                '--interval' => '0.1',
                '--batch'    => '10',
            ]);

            $command->handle();

            /** 普通错误不应调用 pg_drop_replication_slot */
            $dropCalls = array_filter($mockConnection->statements, static function ($s) {
                return false !== strpos($s['query'], 'pg_drop_replication_slot');
            });
            $this->assertCount(0, $dropCalls, 'pg_drop_replication_slot should NOT be called for non-WAL-segment errors');
        }

        public function testPollChangesAdvanceModeAdvancesToPrePeekLsnWhenNoChanges(): void
        {
            $rows = [];

            $connection = new class($rows) extends MockCursorConnection {
                public $selectOneCalls = [];

                public function selectOne($query, array $bindings = [], $useReadPdo = true)
                {
                    $this->selectOneCalls[] = ['query' => $query, 'bindings' => $bindings];

                    if (false !== strpos($query, 'pg_current_wal_lsn')) {
                        return (object) ['lsn' => '0/ABC'];
                    }

                    return null;
                }
            };

            $command = new class() extends WalEventDispatchCommand {
                public $connection;

                protected function getConnection()
                {
                    return $this->connection;
                }
            };

            $this->initializeCommand($command, ['--mode' => 'advance', '--format-version' => '2']);
            $command->connection = $connection;

            $result = self::callMethod($command, 'pollChanges', ['slot_phantom', [], 1000, []]);

            $this->assertFalse($result);
            $this->assertCount(1, $connection->statements);
            $this->assertStringContainsString('pg_replication_slot_advance', $connection->statements[0]['query']);
            $this->assertSame(['slot_phantom', '0/ABC'], $connection->statements[0]['bindings']);
        }

        private function initializeCommand(WalEventDispatchCommand $command, array $options = []): WalEventDispatchCommand
        {
            $command->setLaravel($this->app);

            $input = new ArrayInput($options, $command->getDefinition());
            self::setProperty($command, 'input', $input);

            $bufferedOutput = new BufferedOutput();
            self::setProperty($command, 'output', new OutputStyle($input, $bufferedOutput));

            return $command;
        }

        private function deleteDirectory(string $dir): void
        {
            if (!is_dir($dir)) {
                return;
            }

            $items = scandir($dir);
            if (!is_array($items)) {
                @rmdir($dir);

                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir.DIRECTORY_SEPARATOR.$item;
                if (is_dir($path)) {
                    $this->deleteDirectory($path);
                } else {
                    @unlink($path);
                }
            }

            @rmdir($dir);
        }
    }

    class TestableWalEventDispatchCommand extends WalEventDispatchCommand
    {
        public array $handlers = [];
        public array $partitionMap = [];
        public bool $pollReturn = false;
        public ?\Throwable $pollThrowable = null;
        public array $calls = [];
        public $connection = null;
        public ?EventsDispatcher $eventsDispatcher = null;
        public ?ExceptionHandler $exceptionHandler = null;
        public bool $reconnectCalled = false;

        protected function ensureSlotExists(string $slot): void
        {
            $this->calls[] = ['ensureSlotExists', $slot];
        }

        protected function discoverWalHandlers(): array
        {
            return $this->handlers;
        }

        protected function buildPartitionMap(): array
        {
            return $this->partitionMap;
        }

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];

            if ($this->pollThrowable !== null) {
                $this->shouldRun = false;

                throw $this->pollThrowable;
            }

            $this->shouldRun = false;

            return $this->pollReturn;
        }

        protected function getExceptionHandler(): ExceptionHandler
        {
            if ($this->exceptionHandler !== null) {
                return $this->exceptionHandler;
            }

            return parent::getExceptionHandler();
        }

        protected function getEventsDispatcher(): EventsDispatcher
        {
            if ($this->eventsDispatcher !== null) {
                return $this->eventsDispatcher;
            }

            return parent::getEventsDispatcher();
        }

        protected function getConnection()
        {
            if ($this->connection !== null) {
                return $this->connection;
            }

            return parent::getConnection();
        }

        protected function reconnectDatabase(): void
        {
            $this->reconnectCalled = true;
            $this->shouldRun = false;
        }
    }

    class TestableWalMultiErrorCommand extends TestableWalEventDispatchCommand
    {
        /** @var int 0 means always fail */
        public int $failCount = 0;
        private int $pollAttempt = 0;

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->pollAttempt++;
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];

            if ($this->failCount > 0 && $this->pollAttempt > $this->failCount) {
                $this->shouldRun = false;

                return false;
            }

            if ($this->pollThrowable !== null) {
                throw $this->pollThrowable;
            }

            $this->shouldRun = false;

            return false;
        }

        protected function reconnectDatabase(): void
        {
            $this->reconnectCalled = true;
        }
    }

    class TestableWalEnsureSlotFailsCommand extends TestableWalEventDispatchCommand
    {
        public ?\Throwable $ensureSlotException = null;
        public bool $ensureSlotCalledInErrorPath = false;
        private bool $firstCall = true;

        protected function ensureSlotExists(string $slot): void
        {
            if ($this->firstCall) {
                $this->firstCall = false;
                parent::ensureSlotExists($slot);

                return;
            }

            $this->ensureSlotCalledInErrorPath = true;

            if ($this->ensureSlotException !== null) {
                throw $this->ensureSlotException;
            }
        }
    }

    class DummyWalHandler extends \HughCube\Laravel\Knight\Database\Eloquent\Model implements HasWalHandler
    {
        use \HughCube\Laravel\Knight\Database\Eloquent\Traits\HasWalHandlerTrait;

        protected $table = 'users';

        protected $fillable = ['id', 'name'];

        public function onKnightModelChanged(): void
        {
        }
    }

    class DummyWalHandlerNoKey extends \HughCube\Laravel\Knight\Database\Eloquent\Model implements HasWalHandler
    {
        use \HughCube\Laravel\Knight\Database\Eloquent\Traits\HasWalHandlerTrait;

        protected $table = 'users';

        protected $fillable = ['id', 'name'];

        public function onKnightModelChanged(): void
        {
        }
    }

    class TestFakeTelescope
    {
        public static $recording = true;
        public static $storeCalled = false;
        public static $storeThrows = false;

        public static function isRecording()
        {
            return static::$recording;
        }

        public static function store($repository)
        {
            static::$storeCalled = true;
            if (static::$storeThrows) {
                throw new \RuntimeException('telescope store failed');
            }
        }
    }

    class TestableWalMemoryExceededCommand extends TestableWalEventDispatchCommand
    {
        public bool $memoryExitTriggered = false;

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];

            return $this->pollReturn;
        }

        protected function isMemoryExceeded($memoryLimit): bool
        {
            $this->memoryExitTriggered = true;

            return true;
        }
    }

    class TestableWalPartitionRefreshCommand extends TestableWalEventDispatchCommand
    {
        public bool $partitionMapRebuilt = false;

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];

            /** 第一次 poll 后停止 */
            $this->shouldRun = false;

            return $this->pollReturn;
        }

        protected function buildPartitionMap(): array
        {
            $this->partitionMapRebuilt = true;

            return [];
        }
    }

    class TestableWalSegmentRemovedCommand extends TestableWalEventDispatchCommand
    {
        private int $pollAttempt = 0;

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->pollAttempt++;
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];

            if ($this->pollAttempt === 1 && $this->pollThrowable !== null) {
                throw $this->pollThrowable;
            }

            /** 第二次 poll 正常返回并停止 */
            $this->shouldRun = false;

            return false;
        }

        protected function reconnectDatabase(): void
        {
            $this->reconnectCalled = true;
            /** 不设置 shouldRun = false，让 handle() 继续走 WAL segment 检测 + ensureSlotExists 逻辑 */
        }
    }

    class TestableWalSlotLagCommand extends TestableWalEventDispatchCommand
    {
        public bool $slotHealthReturnsTrue = false;
        public bool $slotHealthChecked = false;

        protected function pollChanges(string $slot, array $handlers, int $batch, array $partitionMap): bool
        {
            $this->calls[] = ['pollChanges', $slot, $batch, count($handlers), count($partitionMap)];
            $this->shouldRun = false;

            return $this->pollReturn;
        }

        protected function checkSlotHealth($slot, $warningMB = 0, $criticalMB = 0)
        {
            $this->slotHealthChecked = true;

            return $this->slotHealthReturnsTrue;
        }
    }

    /**
     * Mock Connection for cursor-based pollChanges tests.
     * 只保留 pollChanges 实际会用到的最小接口。
     */
    class MockCursorConnection
    {
        /** @var array */
        public $rows;
        /** @var array */
        public $statements = [];
        /** @var bool */
        public $advanceThrows = false;
        /** @var array */
        public $cursorCalls = [];
        /** @var bool */
        public $writeConnectionForced = false;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function useWriteConnectionWhenReading($value = true)
        {
            $this->writeConnectionForced = $value;

            return $this;
        }

        public function selectOne($query, array $bindings = [], $useReadPdo = true)
        {
            if (false !== strpos($query, 'pg_current_wal_lsn')) {
                return (object) ['lsn' => '0/0'];
            }

            return null;
        }

        public function cursor($query, array $bindings = [], $useReadPdo = true, array $fetchUsing = [])
        {
            $this->cursorCalls[] = [
                'query'      => $query,
                'bindings'   => $bindings,
                'useReadPdo' => $useReadPdo,
                'fetchUsing' => $fetchUsing,
            ];

            foreach ($this->rows as $row) {
                yield $row;
            }
        }

        public function statement($query, array $bindings = [])
        {
            $this->statements[] = ['query' => $query, 'bindings' => $bindings];
            if ($this->advanceThrows) {
                throw new \RuntimeException('advance failed');
            }

            return true;
        }
    }
}
