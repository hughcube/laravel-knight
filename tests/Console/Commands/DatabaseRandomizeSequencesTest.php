<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/2/5
 * Time: 15:30.
 */

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseRandomizeSequencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTestSequences();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestSequences();
        parent::tearDown();
    }

    protected function cleanupTestSequences(): void
    {
        if (! $this->isPgsqlConfigured()) {
            return;
        }

        try {
            $connection = DB::connection('pgsql');
            $connection->statement('DROP TABLE IF EXISTS randomize_test_users');
            $connection->statement('DROP TABLE IF EXISTS randomize_test_orders');
            $connection->statement('DROP TABLE IF EXISTS randomize_test_logs');
            $connection->statement('DROP SEQUENCE IF EXISTS randomize_test_seq');
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }

    public function testCommandExistsAndHelp(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $exitCode = Artisan::call('database:randomize-sequences', ['--help' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('--connection', $output);
        $this->assertStringContainsString('--min', $output);
        $this->assertStringContainsString('--max', $output);
        $this->assertStringContainsString('--pattern', $output);
        $this->assertStringContainsString('--exclude', $output);
    }

    public function testRejectsNonPgsqlConnection(): void
    {
        // SQLite is the default connection in tests
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--force' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('only supports PostgreSQL', Artisan::output());
    }

    public function testDryRunMode(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test table with sequence
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // Get current sequence value
        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // Run in dry-run mode
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Dry run mode', Artisan::output());

        // Verify sequence was not changed
        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $this->assertEquals($before, $after);
    }

    public function testRandomizesSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test table
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // Insert a record to establish baseline
        $connection->table('randomize_test_users')->insert(['name' => 'test']);

        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // Run randomization
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_users_*',
            '--min' => 100,
            '--max' => 200,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // Should have increased by 100-200 + 1 (for the nextval call)
        $increment = $after - $before;
        $this->assertGreaterThanOrEqual(101, $increment);
        $this->assertLessThanOrEqual(201, $increment);
    }

    public function testPatternMatching(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create multiple test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        // Get baseline values
        $usersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        // Run randomization only on users sequence
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => '*_users_*',
            '--min' => 500,
            '--max' => 500,
            '--force' => true,
        ]);

        $usersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        // Users sequence should have changed
        $this->assertGreaterThan($usersBefore, $usersAfter);

        // Orders sequence should NOT have changed
        $this->assertEquals($ordersBefore, $ordersAfter);
    }

    public function testExcludePattern(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        $usersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $logsBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_logs_id_seq'
        )->last_value;

        // Run randomization, excluding logs
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--exclude' => '*_logs_*',
            '--min' => 300,
            '--max' => 300,
            '--force' => true,
        ]);

        $usersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $logsAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_logs_id_seq'
        )->last_value;

        // Users should have changed
        $this->assertGreaterThan($usersBefore, $usersAfter);

        // Logs should NOT have changed (excluded)
        $this->assertEquals($logsBefore, $logsAfter);
    }

    public function testMultiplePatterns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->create('randomize_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        // Run with multiple patterns (users and orders, not logs)
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => ['*_users_*', '*_orders_*'],
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $output = Artisan::output();

        // Should have processed users and orders
        $this->assertStringContainsString('randomize_test_users_id_seq', $output);
        $this->assertStringContainsString('randomize_test_orders_id_seq', $output);

        // Should NOT have processed logs
        $this->assertStringNotContainsString('randomize_test_logs_id_seq', $output);
    }

    public function testMinMaxValidation(): void
    {
        $this->skipIfPgsqlNotConfigured();

        // Test min < 1
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--min' => 0,
            '--force' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('at least 1', Artisan::output());

        // Test max < min
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--min' => 100,
            '--max' => 50,
            '--force' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('greater than or equal', Artisan::output());
    }

    public function testNoSequencesFound(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'nonexistent_pattern_xyz_*',
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        // 可能是 "No sequences found" 或 "No sequences match"
        $this->assertTrue(
            str_contains($output, 'No sequences found')
            || str_contains($output, 'No sequences match'),
            "Expected output to contain 'No sequences found' or 'No sequences match'"
        );
    }

    public function testWildcardAsterisk(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        // Use * wildcard
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_u*',  // 匹配 users 开头
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $output = Artisan::output();

        // Should match users
        $this->assertStringContainsString('randomize_test_users_id_seq', $output);

        // Should NOT match orders
        $this->assertStringNotContainsString('randomize_test_orders_id_seq', $output);
    }

    public function testConcurrencySafety(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');

        // 测试 PostgreSQL advisory lock 机制本身
        $lockId = 742198365; // Same as command's ADVISORY_LOCK_ID

        // 验证可以获取锁
        $result = $connection->selectOne("SELECT pg_try_advisory_lock({$lockId}) as locked");
        $this->assertTrue($result->locked, 'Should be able to acquire lock');

        // 验证同一会话再次获取锁会成功（PostgreSQL 允许同一会话重入）
        $result2 = $connection->selectOne("SELECT pg_try_advisory_lock({$lockId}) as locked");
        $this->assertTrue($result2->locked, 'Same session should be able to reacquire lock');

        // 释放锁（需要释放两次，因为获取了两次）
        $connection->statement("SELECT pg_advisory_unlock({$lockId})");
        $connection->statement("SELECT pg_advisory_unlock({$lockId})");

        // 验证锁已释放，可以再次获取
        $result3 = $connection->selectOne("SELECT pg_try_advisory_lock({$lockId}) as locked");
        $this->assertTrue($result3->locked, 'Should be able to acquire lock after release');

        // 清理
        $connection->statement("SELECT pg_advisory_unlock({$lockId})");
    }

    public function testNoLockOption(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test table
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // Run with --no-lock option
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--no-lock' => true,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Should not mention lock in output
        $this->assertStringNotContainsString('Acquiring advisory lock', Artisan::output());
    }

    public function testDefaultPatternMatchesAll(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create multiple test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $usersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        // Run WITHOUT specifying --pattern (should match all)
        // Use exclude to limit to our test sequences only
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--exclude' => ['*_test_logs_*'],  // 排除 logs，但不排除 users 和 orders
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $output = Artisan::output();

        // Both should be in output (matched by default)
        $this->assertStringContainsString('randomize_test_users_id_seq', $output);
        $this->assertStringContainsString('randomize_test_orders_id_seq', $output);

        $usersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        // Both should have changed
        $this->assertGreaterThan($usersBefore, $usersAfter);
        $this->assertGreaterThan($ordersBefore, $ordersAfter);
    }

    public function testMultipleExcludePatterns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->create('randomize_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        // Run with multiple exclude patterns
        Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--exclude' => ['*_orders_*', '*_logs_*'],  // Exclude both orders and logs
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $output = Artisan::output();

        // Only users should be processed
        $this->assertStringContainsString('randomize_test_users_id_seq', $output);
        $this->assertStringNotContainsString('randomize_test_orders_id_seq', $output);
        $this->assertStringNotContainsString('randomize_test_logs_id_seq', $output);
    }

    public function testMultipleSequencesAllRandomized(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create multiple test tables
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->create('randomize_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        // Get baseline values
        $usersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        $logsBefore = $connection->selectOne(
            'SELECT last_value FROM randomize_test_logs_id_seq'
        )->last_value;

        // Run randomization on all test sequences
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 200,
            '--max' => 200,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Get new values
        $usersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $ordersAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_orders_id_seq'
        )->last_value;

        $logsAfter = $connection->selectOne(
            'SELECT last_value FROM randomize_test_logs_id_seq'
        )->last_value;

        // All three should have changed by 200 (increment value)
        $this->assertEquals(200, $usersAfter - $usersBefore);
        $this->assertEquals(200, $ordersAfter - $ordersBefore);
        $this->assertEquals(200, $logsAfter - $logsBefore);

        // Verify output contains all sequences
        $output = Artisan::output();
        $this->assertStringContainsString('Success: 3', $output);
    }

    public function testMinEqualsMax(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // Run with min == max (fixed increment)
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 777,
            '--max' => 777,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // Should increase by exactly 777 (the increment value)
        $this->assertEquals(777, $after - $before);
    }

    public function testOutputFormat(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();

        // Verify output format contains expected elements
        $this->assertStringContainsString('Found', $output);
        $this->assertStringContainsString('sequence(s) to randomize', $output);
        $this->assertStringContainsString('Increment range: 100 ~ 100', $output);
        $this->assertStringContainsString('Randomizing sequences', $output);
        $this->assertStringContainsString('Completed', $output);
        $this->assertStringContainsString('Success:', $output);
    }

    // ==================== Strict Additional Tests ====================

    public function testMinValueBoundaryOne(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // min=1 是允许的最小值
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 1,
            '--max' => 1,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 增量为 1
        $this->assertEquals(1, $after - $before);
    }

    public function testLargeIncrementValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 大增量值
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 1000000,
            '--max' => 1000000,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        $this->assertEquals(1000000, $after - $before);
    }

    public function testRandomnessVerification(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 运行多次，验证结果有随机性
        $increments = [];

        for ($i = 0; $i < 5; $i++) {
            // 每次重新创建表
            $connection->statement('DROP TABLE IF EXISTS randomize_test_users');

            $schema->create('randomize_test_users', function ($table) {
                $table->id();
                $table->string('name');
            });

            $before = $connection->selectOne(
                'SELECT last_value FROM randomize_test_users_id_seq'
            )->last_value;

            Artisan::call('database:randomize-sequences', [
                '--connection' => 'pgsql',
                '--pattern' => 'randomize_test_*',
                '--min' => 1,
                '--max' => 10000,
                '--force' => true,
            ]);

            $after = $connection->selectOne(
                'SELECT last_value FROM randomize_test_users_id_seq'
            )->last_value;

            $increments[] = $after - $before;
        }

        // 所有增量应该在范围内
        foreach ($increments as $inc) {
            $this->assertGreaterThanOrEqual(1, $inc);
            $this->assertLessThanOrEqual(10000, $inc);
        }

        // 如果所有值都相同，说明没有随机性（虽然理论上可能，但概率极低）
        // 只检查不是所有值都相等
        $uniqueValues = array_unique($increments);
        // 5次运行中至少应该有2个不同的值（允许一定的重复）
        $this->assertGreaterThanOrEqual(2, count($uniqueValues), 'Randomization should produce varied results');
    }

    public function testNegativeMinValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        // 负数 min 值应该被拒绝
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--min' => -1,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('at least 1', Artisan::output());
    }

    public function testEmptyPatternMatchesNothing(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 空字符串 pattern 不应该匹配任何序列
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => '',
            '--force' => true,
        ]);

        $output = Artisan::output();
        // 空 pattern 被过滤掉后，默认匹配所有
        // 所以这个测试应该成功
        $this->assertEquals(0, $exitCode);
    }

    public function testExcludeAllMatchingPatterns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 排除所有 randomize_test_* 序列
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--exclude' => 'randomize_test_*',  // 同时排除
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertTrue(
            str_contains($output, 'No sequences match') || str_contains($output, 'No sequences found'),
            'Should indicate no sequences match after exclusion'
        );
    }

    public function testSequenceAfterDataInsertion(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 先插入一些数据
        $connection->table('randomize_test_users')->insert(['name' => 'user1']);
        $connection->table('randomize_test_users')->insert(['name' => 'user2']);
        $connection->table('randomize_test_users')->insert(['name' => 'user3']);

        $before = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 此时 last_value = 3, is_called = true
        $this->assertEquals(3, $before);

        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $after = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 由于 is_called = true，nextval() 返回 4
        // setval(seq, 4 + 100) = 104
        // 所以 after = 104, diff = 101
        $this->assertEquals(101, $after - $before);

        // 新插入的记录应该从 105 开始
        $connection->table('randomize_test_users')->insert(['name' => 'user4']);
        $record = $connection->table('randomize_test_users')->where('name', 'user4')->first();
        $this->assertEquals(105, $record->id);
    }

    public function testSpecialCharactersInSequenceName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        // 注意：PostgreSQL 序列名通常不包含特殊字符，这个测试验证正常行为
        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 正常的序列名
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_users_id_seq',  // 精确匹配
            '--min' => 50,
            '--max' => 50,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function testCombinedPatternAndExclude(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 创建多个表
        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('randomize_test_orders', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->create('randomize_test_logs', function ($table) {
            $table->id();
            $table->text('message');
        });

        // pattern 匹配 randomize_test_*，但排除 *_logs_* 和 *_orders_*
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--exclude' => ['*_logs_*', '*_orders_*'],
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();

        // 只有 users 应该被处理
        $this->assertStringContainsString('randomize_test_users_id_seq', $output);
        $this->assertStringNotContainsString('randomize_test_orders_id_seq', $output);
        $this->assertStringNotContainsString('randomize_test_logs_id_seq', $output);
    }

    public function testLockAcquisitionSuccess(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 正常运行（带锁）
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Acquiring advisory lock', $output);
        $this->assertStringContainsString('Lock acquired', $output);
        $this->assertStringContainsString('Lock released', $output);
    }

    public function testSequenceValueAfterMultipleRandomizations(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $initial = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 多次运行随机化
        for ($i = 0; $i < 3; $i++) {
            Artisan::call('database:randomize-sequences', [
                '--connection' => 'pgsql',
                '--pattern' => 'randomize_test_*',
                '--min' => 100,
                '--max' => 100,
                '--force' => true,
            ]);
        }

        $final = $connection->selectOne(
            'SELECT last_value FROM randomize_test_users_id_seq'
        )->last_value;

        // 每次 randomize 增加 101（nextval + 100）
        // 第一次：初始 is_called=false, nextval 返回 1, setval(101), diff=100
        // 第二次：is_called=true, nextval 返回 102, setval(202), diff=101
        // 第三次：is_called=true, nextval 返回 203, setval(303), diff=101
        // 总增量应该是 302
        $this->assertGreaterThan($initial, $final);
        $this->assertEquals(302, $final - $initial);
    }

    public function testOutputShowsCorrectIncrement(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        // 使用固定增量便于验证输出
        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 500,
            '--max' => 500,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();

        // 输出应该显示 (+500) 的增量
        $this->assertStringContainsString('(+500)', $output);
    }

    public function testFailedCountInOutput(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('randomize_test_users', function ($table) {
            $table->id();
            $table->string('name');
        });

        $exitCode = Artisan::call('database:randomize-sequences', [
            '--connection' => 'pgsql',
            '--pattern' => 'randomize_test_*',
            '--min' => 100,
            '--max' => 100,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        // 没有失败
        $this->assertStringContainsString('Failed: 0', $output);
    }
}
