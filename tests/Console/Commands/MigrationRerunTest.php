<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/1/26
 * Time: 10:30.
 */

namespace HughCube\Laravel\Knight\Tests\Console\Commands;

use HughCube\Laravel\Knight\Console\Commands\MigrateRerun;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;

class MigrationRerunTest extends TestCase
{
    /**
     * @var string
     */
    protected $migrationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationPath = __DIR__.'/stubs/migrations';

        // 确保测试表不存在
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_posts');
        Schema::dropIfExists('test_no_down');

        // 确保 migrations 表存在
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
        } else {
            // 清理测试相关的 migration 记录
            DB::table('migrations')->where('migration', 'like', '%test_%')->delete();
        }
    }

    protected function tearDown(): void
    {
        // 清理测试表
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_posts');
        Schema::dropIfExists('test_no_down');

        // 清理 migration 记录
        if (Schema::hasTable('migrations')) {
            DB::table('migrations')->where('migration', 'like', '%test_%')->delete();
        }

        parent::tearDown();
    }

    /**
     * 测试命令基本执行 - 非匿名类 migration.
     */
    public function testRunNonAnonymousMigration()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 验证表已创建
        $this->assertTrue(Schema::hasTable('test_users'));

        // 验证 migration 记录已插入
        $this->assertDatabaseHas('migrations', [
            'migration' => '2024_01_01_000001_create_test_users_table',
        ]);
    }

    /**
     * 测试命令执行 - 匿名类 migration.
     */
    public function testRunAnonymousMigration()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_posts_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 验证表已创建
        $this->assertTrue(Schema::hasTable('test_posts'));

        // 验证 migration 记录已插入
        $this->assertDatabaseHas('migrations', [
            'migration' => '2024_01_01_000002_create_test_posts_table',
        ]);
    }

    /**
     * 测试重复执行 migration（不执行 down）.
     */
    public function testRerunMigrationWithoutDown()
    {
        // 第一次执行
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_users'));

        // 记录第一次的 batch
        $firstBatch = DB::table('migrations')
            ->where('migration', '2024_01_01_000001_create_test_users_table')
            ->value('batch');

        // 删除表但保留 migration 记录，模拟需要重新执行的场景
        Schema::dropIfExists('test_users');

        // 第二次执行（跳过 down，因为表已不存在）
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 验证表重新创建
        $this->assertTrue(Schema::hasTable('test_users'));

        // 验证 batch 递增
        $secondBatch = DB::table('migrations')
            ->where('migration', '2024_01_01_000001_create_test_users_table')
            ->value('batch');

        $this->assertGreaterThan($firstBatch, $secondBatch);
    }

    /**
     * 测试重复执行 migration（执行 down）.
     */
    public function testRerunMigrationWithDown()
    {
        // 第一次执行
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_users'));

        // 第二次执行（使用 --force 会自动执行 down）
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(0);

        // 验证表仍然存在（down + up）
        $this->assertTrue(Schema::hasTable('test_users'));
    }

    /**
     * 测试同时执行多个 migration.
     */
    public function testRunMultipleMigrations()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table', 'create_test_posts_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 验证两个表都已创建
        $this->assertTrue(Schema::hasTable('test_users'));
        $this->assertTrue(Schema::hasTable('test_posts'));

        // 验证两个 migration 记录都已插入
        $this->assertDatabaseHas('migrations', [
            'migration' => '2024_01_01_000001_create_test_users_table',
        ]);
        $this->assertDatabaseHas('migrations', [
            'migration' => '2024_01_01_000002_create_test_posts_table',
        ]);
    }

    /**
     * 测试使用关键词匹配 migration.
     */
    public function testRunMigrationByKeyword()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['test_users'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_users'));
    }

    /**
     * 测试 migrations 表不存在时自动创建.
     */
    public function testAutoCreateMigrationsTable()
    {
        // 删除 migrations 表
        Schema::dropIfExists('migrations');

        $this->assertFalse(Schema::hasTable('migrations'));

        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 验证 migrations 表已自动创建
        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('test_users'));
    }

    /**
     * 测试无匹配的 migration.
     */
    public function testNoMatchingMigration()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['non_existing_migration'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(1);
    }

    /**
     * 测试无效的 migration 路径.
     */
    public function testInvalidMigrationPath()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['test'],
            '--path' => 'non/existing/path',
            '--force' => true,
        ])->assertExitCode(1);
    }

    /**
     * 测试 getMigrationFiles 方法.
     */
    public function testGetMigrationFiles()
    {
        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'getMigrationFiles');
        $method->setAccessible(true);

        $files = $method->invoke($command, $this->migrationPath);

        $this->assertGreaterThanOrEqual(2, $files->count());
        $this->assertArrayHasKey('2024_01_01_000001_create_test_users_table', $files->toArray());
        $this->assertArrayHasKey('2024_01_01_000002_create_test_posts_table', $files->toArray());
    }

    /**
     * 测试 matchMigrations 方法.
     */
    public function testMatchMigrations()
    {
        $command = new MigrateRerun();

        $getMigrationFilesMethod = new ReflectionMethod($command, 'getMigrationFiles');
        $getMigrationFilesMethod->setAccessible(true);
        $allFiles = $getMigrationFilesMethod->invoke($command, $this->migrationPath);

        $matchMigrationsMethod = new ReflectionMethod($command, 'matchMigrations');
        $matchMigrationsMethod->setAccessible(true);

        // 测试精确匹配
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['2024_01_01_000001_create_test_users_table']);
        $this->assertCount(1, $matched);

        // 测试关键词匹配
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['test_users']);
        $this->assertCount(1, $matched);

        // 测试通配匹配
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['test_']);
        $this->assertGreaterThanOrEqual(2, $matched->count());

        // 测试无匹配
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['non_existing']);
        $this->assertCount(0, $matched);
    }

    /**
     * 测试 matchMigrations 方法 - 带 .php 后缀
     */
    public function testMatchMigrationsWithPhpSuffix()
    {
        $command = new MigrateRerun();

        $getMigrationFilesMethod = new ReflectionMethod($command, 'getMigrationFiles');
        $getMigrationFilesMethod->setAccessible(true);
        $allFiles = $getMigrationFilesMethod->invoke($command, $this->migrationPath);

        $matchMigrationsMethod = new ReflectionMethod($command, 'matchMigrations');
        $matchMigrationsMethod->setAccessible(true);

        // 测试带 .php 后缀的匹配（应该被自动移除）
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['2024_01_01_000001_create_test_users_table.php']);
        $this->assertCount(1, $matched);
    }

    /**
     * 测试 matchMigrations 方法 - 空关键词.
     */
    public function testMatchMigrationsWithEmptyKeyword()
    {
        $command = new MigrateRerun();

        $getMigrationFilesMethod = new ReflectionMethod($command, 'getMigrationFiles');
        $getMigrationFilesMethod->setAccessible(true);
        $allFiles = $getMigrationFilesMethod->invoke($command, $this->migrationPath);

        $matchMigrationsMethod = new ReflectionMethod($command, 'matchMigrations');
        $matchMigrationsMethod->setAccessible(true);

        // 测试空关键词
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['', '  ', 'test_users']);
        $this->assertCount(1, $matched);

        // 测试全部为空
        $matched = $matchMigrationsMethod->invoke($command, $allFiles, ['', '  ']);
        $this->assertCount(0, $matched);
    }

    /**
     * 测试 resolveMigrationInstance 方法 - 非匿名类.
     */
    public function testResolveMigrationInstanceNonAnonymous()
    {
        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'resolveMigrationInstance');
        $method->setAccessible(true);

        $file = $this->migrationPath.'/2024_01_01_000001_create_test_users_table.php';
        require_once $file;

        $instance = $method->invoke($command, $file);

        $this->assertNotNull($instance);
        $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $instance);
        $this->assertTrue(method_exists($instance, 'up'));
        $this->assertTrue(method_exists($instance, 'down'));
    }

    /**
     * 测试 resolveMigrationInstance 方法 - 匿名类.
     */
    public function testResolveMigrationInstanceAnonymous()
    {
        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'resolveMigrationInstance');
        $method->setAccessible(true);

        $file = $this->migrationPath.'/2024_01_01_000002_create_test_posts_table.php';

        $instance = $method->invoke($command, $file);

        $this->assertNotNull($instance);
        $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $instance);
        $this->assertTrue(method_exists($instance, 'up'));
        $this->assertTrue(method_exists($instance, 'down'));
    }

    /**
     * 测试 shouldUseTransaction 方法.
     */
    public function testShouldUseTransaction()
    {
        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'shouldUseTransaction');
        $method->setAccessible(true);

        // 普通 migration 默认使用事务
        $migration = new class() extends \Illuminate\Database\Migrations\Migration
        {
            public function up()
            {
            }
        };
        $this->assertTrue($method->invoke($command, $migration));

        // 启用事务的 migration
        $migrationWithTransaction = new class() extends \Illuminate\Database\Migrations\Migration
        {
            public $withinTransaction = true;

            public function up()
            {
            }
        };
        $this->assertTrue($method->invoke($command, $migrationWithTransaction));

        // 禁用事务的 migration
        $migrationNoTransaction = new class() extends \Illuminate\Database\Migrations\Migration
        {
            public $withinTransaction = false;

            public function up()
            {
            }
        };
        $this->assertFalse($method->invoke($command, $migrationNoTransaction));
    }

    /**
     * 测试 isAbsolutePath 方法.
     */
    public function testIsAbsolutePath()
    {
        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'isAbsolutePath');
        $method->setAccessible(true);

        // Unix 绝对路径
        $this->assertTrue($method->invoke($command, '/var/www/html'));
        $this->assertTrue($method->invoke($command, '/home/user/project'));

        // Windows 绝对路径
        $this->assertTrue($method->invoke($command, 'C:\\Users\\test'));
        $this->assertTrue($method->invoke($command, 'D:/Projects/laravel'));
        $this->assertTrue($method->invoke($command, 'c:\\windows'));

        // 相对路径
        $this->assertFalse($method->invoke($command, 'database/migrations'));
        $this->assertFalse($method->invoke($command, './migrations'));
        $this->assertFalse($method->invoke($command, '../migrations'));
        $this->assertFalse($method->invoke($command, 'migrations'));
    }

    /**
     * 测试首次执行时不询问 down（existingRecord = false）.
     */
    public function testFirstRunDoesNotAskForDown()
    {
        // 确保没有 migration 记录
        DB::table('migrations')->where('migration', 'like', '%test_%')->delete();

        // 首次执行，不应该询问 down（因为没有记录）
        // 使用 --force 但不使用 --skip-down，如果有记录会执行 down
        // 但因为没有记录，所以不会执行 down
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_users'));
    }

    /**
     * 测试没有 down 方法的 migration.
     */
    public function testMigrationWithoutDownMethod()
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_no_down_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_no_down'));

        // 手动删除表，测试重复执行
        Schema::dropIfExists('test_no_down');

        // 重复执行也应该成功（因为没有 down 方法，不会尝试回滚）
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_no_down_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('test_no_down'));
    }

    /**
     * 测试空目录.
     */
    public function testEmptyMigrationDirectory()
    {
        $emptyDir = sys_get_temp_dir().'/empty_migrations_'.uniqid();
        mkdir($emptyDir);

        try {
            $this->artisan('migrate:krerun', [
                'migrations' => ['test'],
                '--path' => $emptyDir,
                '--force' => true,
            ])->assertExitCode(1);
        } finally {
            rmdir($emptyDir);
        }
    }

    /**
     * 测试 ensureMigrationsTableExists 表已存在的情况.
     */
    public function testEnsureMigrationsTableExistsWhenTableExists()
    {
        // 确保表存在
        $this->assertTrue(Schema::hasTable('migrations'));

        $command = new MigrateRerun();

        $method = new ReflectionMethod($command, 'ensureMigrationsTableExists');
        $method->setAccessible(true);

        // 调用方法，不应该抛出异常
        $method->invoke($command, DB::connection());

        // 表应该仍然存在
        $this->assertTrue(Schema::hasTable('migrations'));
    }

    /**
     * 测试 down 失败时 --force 模式继续执行.
     */
    public function testDownFailsWithForceModeContinues()
    {
        // 第一次执行
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
            '--skip-down' => true,
        ])->assertExitCode(0);

        // 手动删除表，模拟 down 会失败的场景
        Schema::dropIfExists('test_users');

        // 第二次执行，down 会失败（表不存在），但 --force 模式应该继续
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
            '--force' => true,
        ])->assertExitCode(0);

        // 表应该被重新创建
        $this->assertTrue(Schema::hasTable('test_users'));
    }

    /**
     * 测试多次重复执行同一个 migration.
     */
    public function testMultipleRerunsOfSameMigration()
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->artisan('migrate:krerun', [
                'migrations' => ['create_test_users_table'],
                '--path' => $this->migrationPath,
                '--force' => true,
                '--skip-down' => true,
            ])->assertExitCode(0);

            $this->assertTrue(Schema::hasTable('test_users'));

            $batch = DB::table('migrations')
                ->where('migration', '2024_01_01_000001_create_test_users_table')
                ->value('batch');

            $this->assertEquals($i, $batch, "Batch should be {$i} on iteration {$i}");

            // 删除表以便下次重新创建
            Schema::dropIfExists('test_users');
        }
    }

    /**
     * 测试匿名类 migration 多次执行.
     */
    public function testAnonymousMigrationMultipleReruns()
    {
        for ($i = 1; $i <= 2; $i++) {
            $this->artisan('migrate:krerun', [
                'migrations' => ['create_test_posts_table'],
                '--path' => $this->migrationPath,
                '--force' => true,
                '--skip-down' => true,
            ])->assertExitCode(0);

            $this->assertTrue(Schema::hasTable('test_posts'));

            // 删除表以便下次重新创建
            Schema::dropIfExists('test_posts');
        }
    }

    public function testHandleReturnsErrorWhenInteractiveInputIsEmpty(): void
    {
        $command = new class() extends MigrateRerun
        {
            public function argument($key = null)
            {
                return [];
            }

            protected function askForMigrations(): array
            {
                return [];
            }

            public function error($string, $verbosity = null)
            {
            }
        };

        $this->assertSame(1, $command->handle());
    }

    public function testHandleAbortsWhenUserDoesNotConfirm(): void
    {
        $this->artisan('migrate:krerun', [
            'migrations' => ['create_test_users_table'],
            '--path' => $this->migrationPath,
        ])
            ->expectsConfirmation('Do you want to re-run these migrations?', 'no')
            ->assertExitCode(0);
    }

    public function testHandleStopsAfterFailureWhenUserRejectsContinue(): void
    {
        $tempDir = sys_get_temp_dir().'/krerun_fail_'.md5((string) microtime(true));
        @mkdir($tempDir, 0777, true);
        $migrationFile = $tempDir.'/2024_01_01_000099_temp_fail.php';
        file_put_contents(
            $migrationFile,
            <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up()
    {
        throw new \RuntimeException('temp migration up failed');
    }

    public function down()
    {
    }
};
PHP
        );

        try {
            $this->artisan('migrate:krerun', [
                'migrations' => ['temp_fail'],
                '--path' => $tempDir,
            ])
                ->expectsConfirmation('Do you want to re-run these migrations?', 'yes')
                ->expectsConfirmation('Continue with remaining migrations?', 'no')
                ->assertExitCode(1);
        } finally {
            @unlink($migrationFile);
            @rmdir($tempDir);
        }
    }

    public function testAskForMigrationsReturnsEmptyArrayWhenNoInput(): void
    {
        $command = new class() extends MigrateRerun
        {
            public function ask($question, $default = null)
            {
                return '';
            }
        };

        $result = self::callMethod($command, 'askForMigrations');
        $this->assertSame([], $result);
    }

    public function testAskForMigrationsParsesCommaSeparatedValues(): void
    {
        $command = new class() extends MigrateRerun
        {
            public function ask($question, $default = null)
            {
                return ' user , post,  comment ';
            }
        };

        $result = self::callMethod($command, 'askForMigrations');
        $this->assertSame(['user', 'post', 'comment'], $result);
    }

    public function testHandleDownMethodRunsRollbackWhenConfirmed(): void
    {
        $migration = new class()
        {
            public bool $called = false;

            public function down(): void
            {
                $this->called = true;
            }
        };

        $command = new class() extends MigrateRerun
        {
            public function option($key = null)
            {
                $options = [
                    'skip-down' => false,
                    'force' => false,
                ];

                return $options[$key] ?? null;
            }

            public function confirm($question, $default = false)
            {
                return true;
            }

            public function line($string, $style = null, $verbosity = null)
            {
            }

            public function warn($string, $verbosity = null)
            {
            }

            public function error($string, $verbosity = null)
            {
            }
        };

        self::callMethod($command, 'handleDownMethod', [$migration]);
        $this->assertTrue($migration->called);
    }

    public function testHandleDownMethodFailureCanContinueWhenConfirmed(): void
    {
        $migration = new class()
        {
            public function down(): void
            {
                throw new \RuntimeException('down failed');
            }
        };

        $command = new class() extends MigrateRerun
        {
            private int $confirmCount = 0;

            public function option($key = null)
            {
                $options = [
                    'skip-down' => false,
                    'force' => false,
                ];

                return $options[$key] ?? null;
            }

            public function confirm($question, $default = false)
            {
                $this->confirmCount++;

                return true;
            }

            public function line($string, $style = null, $verbosity = null)
            {
            }

            public function warn($string, $verbosity = null)
            {
            }

            public function error($string, $verbosity = null)
            {
            }
        };

        self::callMethod($command, 'handleDownMethod', [$migration]);
        $this->assertTrue(true);
    }

    public function testHandleDownMethodFailureThrowsWhenUserRejectsContinue(): void
    {
        $migration = new class()
        {
            public function down(): void
            {
                throw new \RuntimeException('down failed');
            }
        };

        $command = new class() extends MigrateRerun
        {
            private int $confirmCount = 0;

            public function option($key = null)
            {
                $options = [
                    'skip-down' => false,
                    'force' => false,
                ];

                return $options[$key] ?? null;
            }

            public function confirm($question, $default = false)
            {
                $this->confirmCount++;

                return $this->confirmCount === 1;
            }

            public function line($string, $style = null, $verbosity = null)
            {
            }

            public function warn($string, $verbosity = null)
            {
            }

            public function error($string, $verbosity = null)
            {
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aborted: down() failed and user chose not to continue');
        self::callMethod($command, 'handleDownMethod', [$migration]);
    }

    public function testResolveMigrationInstanceForNamespacedClass(): void
    {
        $tempFile = sys_get_temp_dir().'/migration_namespaced_'.md5((string) microtime(true)).'.php';
        file_put_contents(
            $tempFile,
            <<<'PHP'
<?php
namespace KnightTempMigration;
class DemoNamespacedMigration extends \Illuminate\Database\Migrations\Migration
{
    public function up() {}
    public function down() {}
}
PHP
        );

        try {
            $command = new MigrateRerun();
            $instance = self::callMethod($command, 'resolveMigrationInstance', [$tempFile]);

            $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $instance);
            $this->assertInstanceOf(\KnightTempMigration\DemoNamespacedMigration::class, $instance);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testResolveMigrationInstanceForAnonymousClass(): void
    {
        $tempFile = sys_get_temp_dir().'/migration_anonymous_'.md5((string) microtime(true)).'.php';
        file_put_contents(
            $tempFile,
            <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up() {}
    public function down() {}
};
PHP
        );

        try {
            $command = new MigrateRerun();
            $instance = self::callMethod($command, 'resolveMigrationInstance', [$tempFile]);

            $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $instance);
        } finally {
            @unlink($tempFile);
        }
    }
}
