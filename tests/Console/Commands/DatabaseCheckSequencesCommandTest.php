<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/3/13
 * Time: 10:30.
 */

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\DatabaseCheckSequencesCommand;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseCheckSequencesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTables();
        parent::tearDown();
    }

    protected function cleanupTestTables(): void
    {
        if (! $this->isPgsqlConfigured()) {
            return;
        }

        try {
            $connection = DB::connection('pgsql');
            $connection->statement('DROP TABLE IF EXISTS chkseq_test_users');
            $connection->statement('DROP TABLE IF EXISTS chkseq_test_orders');
            $connection->statement('DROP TABLE IF EXISTS chkseq_test_logs');
            $connection->statement('DROP TABLE IF EXISTS chkseq_test_no_pk');
            $connection->statement('DROP TABLE IF EXISTS chkseq_test_empty');
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }

    // ==================== 基本功能测试 ====================

    public function testCommandExistsAndHelp(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $exitCode = Artisan::call('database:check-sequences', ['--help' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('--connection', $output);
        $this->assertStringContainsString('--fix', $output);
        $this->assertStringContainsString('--growth', $output);
        $this->assertStringContainsString('--alert', $output);
        $this->assertStringContainsString('--pattern', $output);
        $this->assertStringContainsString('--exclude', $output);
        $this->assertStringContainsString('--force', $output);
    }

    public function testRejectsNonPgsqlConnection(): void
    {
        // SQLite 是默认连接
        $exitCode = Artisan::call('database:check-sequences');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('only supports PostgreSQL', Artisan::output());
    }

    // ==================== 正常表检查测试 ====================

    public function testCheckNormalTable(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 正常插入后: max_id=2, sequence_value=2, is_called=true, nextval=3, 不异常
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('chkseq_test_users', $output);
        $this->assertStringContainsString('Checked:', $output);
        $this->assertStringContainsString('Anomalies: 0', $output);
    }

    public function testCheckHealthyTable(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 插入一条数据后手动推进序列，让 sequence > max_id
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1000)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Anomalies: 0', $output);
    }

    public function testDetectsAnomaly(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 插入数据，然后把序列设回小值，制造异常
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        // max_id = 3, 把序列设为 1
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Anomalies: 1', $output);
        $this->assertStringContainsString('max_id=3', $output);
        $this->assertStringContainsString('sequence_value=1', $output);
    }

    public function testNoAnomalyWhenMaxIdEqualsSequenceValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=2, last_value=2, is_called=true, nextval=3, 不冲突
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 2)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Anomalies: 0', $output);
    }

    // ==================== 修复功能测试 ====================

    public function testFixAnomaly(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 制造异常：max_id=5, sequence=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user4']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user5']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        // 执行修复，growth=100
        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--growth' => 100,
            '--force' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Fix completed', $output);

        // 验证序列已被修复: max_id=5, 所以新值应该是 5 + 100 = 105
        $seqValue = $connection->selectOne(
            'SELECT last_value FROM chkseq_test_users_id_seq'
        )->last_value;

        $this->assertEquals(105, intval($seqValue));
    }

    public function testFixWithDefaultGrowth(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 插入3条数据后把序列设为1，max_id=3 > seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        // 使用默认 growth=10
        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--force' => true,
        ]);

        $seqValue = $connection->selectOne(
            'SELECT last_value FROM chkseq_test_users_id_seq'
        )->last_value;

        // max_id=3, 默认 growth=10, 所以 3 + 10 = 13
        $this->assertEquals(13, intval($seqValue));
    }

    public function testFixWithZeroGrowth(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        // growth=0
        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--growth' => 0,
            '--force' => true,
        ]);

        $seqValue = $connection->selectOne(
            'SELECT last_value FROM chkseq_test_users_id_seq'
        )->last_value;

        // max_id=3, growth=0, 所以 3 + 0 = 3
        $this->assertEquals(3, intval($seqValue));
    }

    public function testFixEnsuresInsertWorksAfterFix(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 制造异常: max_id=3, seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        // 修复: seq = max_id(3) + growth(10) = 13
        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--growth' => 10,
            '--force' => true,
        ]);

        // 修复后应该能正常插入, nextval=14
        $connection->table('chkseq_test_users')->insert(['name' => 'user4']);
        $newRecord = $connection->table('chkseq_test_users')->where('name', 'user4')->first();
        $this->assertNotNull($newRecord);
        $this->assertEquals(14, $newRecord->id);
    }

    public function testFixMultipleAnomalies(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('chkseq_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        // 制造两个表的异常
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        $connection->table('chkseq_test_orders')->insert(['title' => 'order1']);
        $connection->table('chkseq_test_orders')->insert(['title' => 'order2']);
        $connection->table('chkseq_test_orders')->insert(['title' => 'order3']);
        $connection->statement("SELECT setval('chkseq_test_orders_id_seq', 1)");

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_*',
            '--fix' => true,
            '--growth' => 50,
            '--force' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Anomalies: 2', $output);
        $this->assertStringContainsString('Fix completed', $output);

        // 验证两个表都修复了
        $usersSeq = $connection->selectOne('SELECT last_value FROM chkseq_test_users_id_seq')->last_value;
        $ordersSeq = $connection->selectOne('SELECT last_value FROM chkseq_test_orders_id_seq')->last_value;

        $this->assertEquals(52, intval($usersSeq));  // 2 + 50
        $this->assertEquals(53, intval($ordersSeq)); // 3 + 50
    }

    public function testNoFixWhenNoAnomalies(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 正常状态: 插入后序列值正常
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1000)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringNotContainsString('Fix completed', $output);
        $this->assertStringContainsString('Anomalies: 0', $output);
    }

    // ==================== 告警功能测试 ====================

    public function testAlertSendsErrorLog(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 制造异常: max_id=3, seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        $handler = $this->setupTestLogHandler();

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--alert' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Alert sent', $output);

        $this->assertLogContains($handler, 'error', 'sequence anomaly detected');
    }

    public function testNoAlertWhenNoAnomalies(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1000)");

        $handler = $this->setupTestLogHandler();

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--alert' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringNotContainsString('Alert sent', $output);
    }

    public function testNoAlertWithoutFlag(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 制造异常: max_id=2, seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            // 不传 --alert
        ]);

        $output = Artisan::output();
        $this->assertStringNotContainsString('Alert sent', $output);
    }

    // ==================== 过滤测试 ====================

    public function testPatternMatching(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('chkseq_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('chkseq_test_users', $output);
        $this->assertStringNotContainsString('chkseq_test_orders', $output);
    }

    public function testExcludePattern(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('chkseq_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_*',
            '--exclude' => '*_logs',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('chkseq_test_users', $output);
        $this->assertStringNotContainsString('chkseq_test_logs', $output);
    }

    public function testMultiplePatterns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('chkseq_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->create('chkseq_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => ['chkseq_test_users', 'chkseq_test_orders'],
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('chkseq_test_users', $output);
        $this->assertStringContainsString('chkseq_test_orders', $output);
        $this->assertStringNotContainsString('chkseq_test_logs', $output);
    }

    public function testExcludeAllMatching(): void
    {
        $this->skipIfPgsqlNotConfigured();

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_nonexistent_*',
        ]);

        $output = Artisan::output();
        $this->assertTrue(
            str_contains($output, 'No tables match') || str_contains($output, 'No tables found'),
            'Should indicate no tables found or match'
        );
    }

    // ==================== 跳过特殊表测试 ====================

    public function testSkipsTableWithoutPrimaryKey(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $connection->statement('CREATE TABLE chkseq_test_no_pk (name TEXT, value INT)');

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_no_pk',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('no primary key', $output);
        $this->assertStringContainsString('Skipped: 1', $output);
    }

    public function testSkipsTableWithoutSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        // 创建一个有主键但没有序列的表
        $connection->statement('CREATE TABLE chkseq_test_no_pk (id INT PRIMARY KEY, name TEXT)');

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_no_pk',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('no sequence found', $output);
        $this->assertStringContainsString('Skipped: 1', $output);
    }

    // ==================== 空表测试 ====================

    public function testEmptyTable(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_empty', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 空表: max_id=0, sequence last_value=1 (初始值)
        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_empty',
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Anomalies: 0', $output);
    }

    // ==================== 内部方法测试 ====================

    public function testGetTablesMethod(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $command = new DatabaseCheckSequencesCommand();

        $tables = $this->callMethod($command, 'getTables', [$connection]);
        $this->assertContains('chkseq_test_users', $tables->toArray());
    }

    public function testGetPrimaryKeyColumnMethod(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $command = new DatabaseCheckSequencesCommand();

        $pk = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'chkseq_test_users']);
        $this->assertEquals('id', $pk);
    }

    public function testGetPrimaryKeyColumnReturnsNullForNoPk(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $connection->statement('CREATE TABLE chkseq_test_no_pk (name TEXT, value INT)');

        $command = new DatabaseCheckSequencesCommand();

        $pk = $this->callMethod($command, 'getPrimaryKeyColumn', [$connection, 'chkseq_test_no_pk']);
        $this->assertNull($pk);
    }

    public function testGetSequenceNameMethod(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $command = new DatabaseCheckSequencesCommand();

        $seqName = $this->callMethod($command, 'getSequenceName', [$connection, 'chkseq_test_users', 'id']);
        $this->assertNotNull($seqName);
        $this->assertStringContainsString('chkseq_test_users', $seqName);
    }

    public function testGetMaxIdMethod(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $command = new DatabaseCheckSequencesCommand();

        // 空表
        $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'chkseq_test_users', 'id']);
        $this->assertEquals(0, $maxId);

        // 插入数据
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);

        $maxId = $this->callMethod($command, 'getMaxId', [$connection, 'chkseq_test_users', 'id']);
        $this->assertEquals(3, $maxId);
    }

    public function testGetSequenceCurrentValueMethod(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $command = new DatabaseCheckSequencesCommand();

        // 设置已知值
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 999)");

        $value = $this->callMethod($command, 'getSequenceCurrentValue', [$connection, 'chkseq_test_users_id_seq']);
        $this->assertEquals(999, $value);
    }

    // ==================== 返回值测试 ====================

    public function testReturnZeroWhenNoAnomalies(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1000)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function testReturnOneWhenAnomaliesExist(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=2 > seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $this->assertEquals(1, $exitCode);
    }

    public function testReturnOneEvenAfterFix(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=2 > seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        // 即使修复了，exit code 仍然为 1 表示检测到了异常
        $exitCode = Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--force' => true,
        ]);

        $this->assertEquals(1, $exitCode);
    }

    // ==================== 输出格式测试 ====================

    public function testOutputShowsCheckedCount(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('chkseq_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_*',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Checking 2 table(s)', $output);
        $this->assertStringContainsString('Checked: 2', $output);
    }

    public function testOutputShowsDifference(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=10, seq=3, 差值 7
        for ($i = 1; $i <= 10; $i++) {
            $connection->table('chkseq_test_users')->insert(['name' => "user{$i}"]);
        }
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 3)");

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('max_id=10', $output);
        $this->assertStringContainsString('sequence_value=3', $output);
    }

    // ==================== fix + alert 组合测试 ====================

    public function testFixAndAlertTogether(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=3 > seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user3']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        $handler = $this->setupTestLogHandler();

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--alert' => true,
            '--growth' => 20,
            '--force' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('Alert sent', $output);
        $this->assertStringContainsString('Fix completed', $output);

        $this->assertLogContains($handler, 'error', 'sequence anomaly detected');

        // 验证修复: max_id=3 + growth=20 = 23
        $seqValue = $connection->selectOne('SELECT last_value FROM chkseq_test_users_id_seq')->last_value;
        $this->assertEquals(23, intval($seqValue));
    }

    // ==================== 大 growth 值测试 ====================

    public function testLargeGrowthValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('chkseq_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // max_id=2 > seq=1
        $connection->table('chkseq_test_users')->insert(['name' => 'user1']);
        $connection->table('chkseq_test_users')->insert(['name' => 'user2']);
        $connection->statement("SELECT setval('chkseq_test_users_id_seq', 1)");

        Artisan::call('database:check-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'chkseq_test_users',
            '--fix' => true,
            '--growth' => 1000000,
            '--force' => true,
        ]);

        $seqValue = $connection->selectOne('SELECT last_value FROM chkseq_test_users_id_seq')->last_value;
        $this->assertEquals(1000002, intval($seqValue)); // 2 + 1000000
    }
}
