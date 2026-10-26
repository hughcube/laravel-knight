<?php

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\DatabaseResetAutoIncrementStartId;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseResetAutoIncrementStartIdTest extends TestCase
{
    public function testCommandSignatureAndDescription(): void
    {
        $command = new DatabaseResetAutoIncrementStartId();

        $this->assertStringContainsString('database:reset-auto-increment-start-id', $command->getName());
    }

    public function testGetTablesForMysql(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');
        $command = new DatabaseResetAutoIncrementStartId();

        $tables = $this->callMethod($command, 'getTables', [$connection, 'mysql']);

        $this->assertIsIterable($tables);
    }

    public function testGetTablesForPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');
        $command = new DatabaseResetAutoIncrementStartId();

        $tables = $this->callMethod($command, 'getTables', [$connection, 'pgsql']);

        $this->assertIsIterable($tables);
    }

    public function testGetPrimaryKeyColumnForMysql(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $primaryKey = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'mysql', $tableName]);

            $this->assertEquals('id', $primaryKey);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testGetPrimaryKeyColumnForPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE \"{$tableName}\" (
                \"id\" BIGSERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $primaryKey = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'pgsql', $tableName]);

            $this->assertEquals('id', $primaryKey);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
    }

    public function testGetMaxIdForMysql(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test1'), ('test2'), ('test3')");

            $command = new DatabaseResetAutoIncrementStartId();
            $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'mysql', $tableName, 'id']);

            $this->assertEquals(3, $maxId);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testGetMaxIdForPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE \"{$tableName}\" (
                \"id\" BIGSERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO \"{$tableName}\" (\"name\") VALUES ('test1'), ('test2'), ('test3')");

            $command = new DatabaseResetAutoIncrementStartId();
            $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'pgsql', $tableName, 'id']);

            $this->assertEquals(3, $maxId);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
    }

    public function testGetMaxIdForEmptyTable(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'mysql', $tableName, 'id']);

            $this->assertEquals(0, $maxId);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testSetAutoIncrementForMysql(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $this->callMethod($command, 'setAutoIncrement', [$connection, 'mysql', $tableName, 'id', 1000]);

            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test')");
            $result = $connection->selectOne("SELECT MAX(`id`) as max_id FROM `{$tableName}`");

            $this->assertEquals(1000, $result->max_id);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testSetAutoIncrementForPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE \"{$tableName}\" (
                \"id\" BIGSERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $this->callMethod($command, 'setAutoIncrement', [$connection, 'pgsql', $tableName, 'id', 1000]);

            $connection->insert("INSERT INTO \"{$tableName}\" (\"name\") VALUES ('test')");
            $result = $connection->selectOne("SELECT MAX(\"id\") as max_id FROM \"{$tableName}\"");

            $this->assertEquals(1000, $result->max_id);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
    }

    public function testGetSequenceNameForPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE \"{$tableName}\" (
                \"id\" BIGSERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $sequenceName = $this->callMethod($command, 'getSequenceName', [$connection, $tableName, 'id']);

            $this->assertNotNull($sequenceName);
            $this->assertStringContainsString($tableName, $sequenceName);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
    }

    public function testMinParameterIsRespected(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test')");

            $command = new DatabaseResetAutoIncrementStartId();
            $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'mysql', $tableName, 'id']);

            $min = 10000;
            $offset = 0;
            $newStartId = max($min, $maxId + $offset);

            $this->assertEquals(10000, $newStartId);
            $this->assertGreaterThan($maxId, $newStartId);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testOffsetParameterIsRespected(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_auto_increment_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test1'), ('test2'), ('test3')");

            $command = new DatabaseResetAutoIncrementStartId();
            $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'mysql', $tableName, 'id']);

            $min = 1;
            $offset = 100;
            $newStartId = max($min, $maxId + $offset);

            $this->assertEquals(103, $newStartId);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testMinAndOffsetCombined(): void
    {
        $min = 1000;
        $offset = 50;
        $maxId = 500;

        $newStartId = max($min, $maxId + $offset);
        $this->assertEquals(1000, $newStartId);

        $maxId = 2000;
        $newStartId = max($min, $maxId + $offset);
        $this->assertEquals(2050, $newStartId);
    }

    public function testTableWithoutPrimaryKeyIsSkipped(): void
    {
        $this->skipIfMysqlNotConfigured();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $tableName = 'test_no_pk_'.uniqid();

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `name` VARCHAR(255) NOT NULL,
                `value` INT NOT NULL
            )
        ");

        try {
            $command = new DatabaseResetAutoIncrementStartId();
            $primaryKey = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'mysql', $tableName]);

            $this->assertNull($primaryKey);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testIntegrationMysql(): void
    {
        $this->skipIfMysqlNotConfigured();

        $tableName = 'test_integration_'.uniqid();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test1'), ('test2')");

            $this->artisan('database:reset-auto-increment-start-id', [
                '--connection' => 'mysql',
                '--table' => $tableName,
                '--min' => 5000,
                '--offset' => 100,
                '--force' => true,
            ]);

            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test3')");
            $result = $connection->selectOne("SELECT MAX(`id`) as max_id FROM `{$tableName}`");

            $this->assertEquals(5000, $result->max_id);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testIntegrationPgsql(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $tableName = 'test_integration_'.uniqid();

        /** @var Connection $connection */
        $connection = DB::connection('pgsql');

        $connection->statement("
            CREATE TABLE \"{$tableName}\" (
                \"id\" BIGSERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL
            )
        ");

        try {
            $connection->insert("INSERT INTO \"{$tableName}\" (\"name\") VALUES ('test1'), ('test2')");

            $this->artisan('database:reset-auto-increment-start-id', [
                '--connection' => 'pgsql',
                '--table' => $tableName,
                '--min' => 5000,
                '--offset' => 100,
                '--force' => true,
            ]);

            $connection->insert("INSERT INTO \"{$tableName}\" (\"name\") VALUES ('test3')");
            $result = $connection->selectOne("SELECT MAX(\"id\") as max_id FROM \"{$tableName}\"");

            $this->assertEquals(5000, $result->max_id);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
        }
    }

    public function testIntegrationWithHighMaxId(): void
    {
        $this->skipIfMysqlNotConfigured();

        $tableName = 'test_high_max_'.uniqid();

        /** @var Connection $connection */
        $connection = DB::connection('mysql');

        $connection->statement("
            CREATE TABLE `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL
            ) AUTO_INCREMENT = 10000
        ");

        try {
            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test1')");

            $this->artisan('database:reset-auto-increment-start-id', [
                '--connection' => 'mysql',
                '--table' => $tableName,
                '--min' => 1000,
                '--offset' => 500,
                '--force' => true,
            ]);

            $connection->insert("INSERT INTO `{$tableName}` (`name`) VALUES ('test2')");
            $result = $connection->selectOne("SELECT MAX(`id`) as max_id FROM `{$tableName}`");

            $this->assertEquals(10500, $result->max_id);
        } finally {
            $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    public function testHandleReportsUnsupportedDriver(): void
    {
        $this->artisan('database:reset-auto-increment-start-id', [
            '--connection' => 'sqlite',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function testHandleMysqlDatabaseOptionUsesDatabaseAndReturnsWhenNoTables(): void
    {
        $pdo = new class()
        {
            public array $queries = [];

            public function exec(string $sql)
            {
                $this->queries[] = $sql;

                return 0;
            }
        };

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDriverName', 'getPdo', 'select'])
            ->getMock();

        $connection->method('getDriverName')->willReturn('mysql');
        $connection->method('getPdo')->willReturn($pdo);
        $connection->method('select')->willReturn([]);

        DB::shouldReceive('connection')->withAnyArgs()->andReturn($connection);

        $this->artisan('database:reset-auto-increment-start-id', [
            '--connection' => 'mysql',
            '--database' => 'local_test',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertContains('use `local_test`;', $pdo->queries);
    }

    public function testHandleReportsSpecificTableNotFound(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDriverName', 'select'])
            ->getMock();

        $connection->method('getDriverName')->willReturn('mysql');
        $connection->method('select')->willReturn([
            (object) ['Tables_in_local_test' => 'users'],
        ]);

        DB::shouldReceive('connection')->withAnyArgs()->andReturn($connection);

        $this->artisan('database:reset-auto-increment-start-id', [
            '--connection' => 'mysql',
            '--table' => 'not_exists',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function testGetPrimaryKeyColumnForPgsqlReturnsNullWhenNoPrimaryKey(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select'])
            ->getMock();

        $connection->method('select')->willReturn([]);

        $command = new DatabaseResetAutoIncrementStartId();
        $primaryKey = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'pgsql', 'users']);

        $this->assertNull($primaryKey);
    }

    public function testProcessTableReturnsWhenPrimaryKeyMissing(): void
    {
        $command = new class() extends DatabaseResetAutoIncrementStartId
        {
            public bool $setAutoIncrementCalled = false;

            protected function getPrimaryKeyColumn(Connection $connection, $driver, $table)
            {
                return null;
            }

            protected function setAutoIncrement(Connection $connection, $driver, $table, $primaryKey, $startId): void
            {
                $this->setAutoIncrementCalled = true;
            }
        };

        $this->initializeCommand($command);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->callMethod($command, 'processTable', [$connection, 'mysql', 'users', 1, 0, true]);

        $this->assertFalse($command->setAutoIncrementCalled);
    }

    public function testProcessTableSkipsWhenNotForcedAndNotConfirmed(): void
    {
        $command = new class() extends DatabaseResetAutoIncrementStartId
        {
            public bool $setAutoIncrementCalled = false;

            protected function getPrimaryKeyColumn(Connection $connection, $driver, $table)
            {
                return 'id';
            }

            protected function getMaxId(Connection $connection, $driver, $table, $primaryKey): int
            {
                return 10;
            }

            public function confirm($question, $default = false)
            {
                return false;
            }

            protected function setAutoIncrement(Connection $connection, $driver, $table, $primaryKey, $startId): void
            {
                $this->setAutoIncrementCalled = true;
            }
        };

        $this->initializeCommand($command);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->callMethod($command, 'processTable', [$connection, 'mysql', 'users', 1, 0, false]);

        $this->assertFalse($command->setAutoIncrementCalled);
    }

    public function testSetAutoIncrementForPgsqlReturnsWhenNoSequenceFound(): void
    {
        $command = new class() extends DatabaseResetAutoIncrementStartId
        {
            protected function getSequenceName(Connection $connection, $table, $column)
            {
                return null;
            }
        };

        $this->initializeCommand($command);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['statement'])
            ->getMock();

        $connection->expects($this->never())->method('statement');

        $this->callMethod($command, 'setAutoIncrement', [$connection, 'pgsql', 'users', 'id', 100]);
    }

    public function testGetSequenceNameFallsBackToColumnDefault(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectOne'])
            ->getMock();

        $connection->expects($this->exactly(2))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['sequence_name' => null],
                (object) ['column_default' => "nextval('public.users_id_seq'::regclass)"]
            );

        $command = new DatabaseResetAutoIncrementStartId();
        $sequence = $this->callMethod($command, 'getSequenceName', [$connection, 'users', 'id']);

        $this->assertSame('public.users_id_seq', $sequence);
    }

    public function testGetSequenceNameFallsBackToDefaultConvention(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectOne'])
            ->getMock();

        $connection->expects($this->exactly(3))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['sequence_name' => null],
                (object) ['column_default' => null],
                (object) ['exists' => true]
            );

        $command = new DatabaseResetAutoIncrementStartId();
        $sequence = $this->callMethod($command, 'getSequenceName', [$connection, 'users', 'id']);

        $this->assertSame('users_id_seq', $sequence);
    }

    public function testGetSequenceNameFallsBackToDependencyLookup(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectOne'])
            ->getMock();

        $connection->expects($this->exactly(4))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['sequence_name' => null],
                (object) ['column_default' => null],
                (object) ['exists' => false],
                (object) ['sequence_name' => 'users_id_custom_seq']
            );

        $command = new DatabaseResetAutoIncrementStartId();
        $sequence = $this->callMethod($command, 'getSequenceName', [$connection, 'users', 'id']);

        $this->assertSame('users_id_custom_seq', $sequence);
    }

    public function testGetSequenceNameReturnsNullWhenAllStrategiesFail(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectOne'])
            ->getMock();

        $connection->expects($this->exactly(4))
            ->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                (object) ['sequence_name' => null],
                (object) ['column_default' => null],
                (object) ['exists' => false],
                (object) ['sequence_name' => null]
            );

        $command = new DatabaseResetAutoIncrementStartId();
        $sequence = $this->callMethod($command, 'getSequenceName', [$connection, 'users', 'id']);

        $this->assertNull($sequence);
    }

    private function initializeCommand(DatabaseResetAutoIncrementStartId $command, array $options = []): DatabaseResetAutoIncrementStartId
    {
        $command->setLaravel($this->app);

        $input = new ArrayInput($options, $command->getDefinition());
        self::setProperty($command, 'input', $input);

        $bufferedOutput = new BufferedOutput();
        self::setProperty($command, 'output', new OutputStyle($input, $bufferedOutput));

        return $command;
    }
}
