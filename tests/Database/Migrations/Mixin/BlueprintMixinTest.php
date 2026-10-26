<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2026/1/20
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Tests\Database\Migrations\Mixin;

use HughCube\Laravel\Knight\Database\Migrations\Mixin\BlueprintMixin;
use HughCube\Laravel\Knight\Database\Migrations\Mixin\PostgresGrammarMixin;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as PostgresSchemaGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BlueprintMixin Tests.
 *
 * Tests for Blueprint extension methods provided by BlueprintMixin.
 */
class BlueprintMixinTest extends TestCase
{
    protected static bool $mixinRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$mixinRegistered) {
            Blueprint::mixin(new BlueprintMixin());
            PostgresSchemaGrammar::mixin(new PostgresGrammarMixin());
            self::$mixinRegistered = true;
        }

        // Clean up test tables before each test
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        // Clean up test tables after each test
        $this->cleanupTestTables();
        parent::tearDown();
    }

    protected function cleanupTestTables(): void
    {
        Schema::dropIfExists('knight_mixin_test');
        Schema::dropIfExists('knight_gin_test');
        Schema::dropIfExists('knight_unique_where_test');
        Schema::dropIfExists('knight_index_where_test');
        Schema::dropIfExists('knight_alter_test');

        if ($this->isPgsqlConfigured()) {
            try {
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_mixin_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_gin_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_gin_btree_test');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_unique_where_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_index_where_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_alter_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_sequence_test_pg');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_shared_seq_table1');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_shared_seq_table2');
                DB::connection('pgsql')->statement('DROP TABLE IF EXISTS knight_shared_seq_table3');
                DB::connection('pgsql')->statement('DROP SEQUENCE IF EXISTS global_test_seq');
                DB::connection('pgsql')->statement('DROP SEQUENCE IF EXISTS shared_id_seq');
                DB::connection('pgsql')->statement('DROP SEQUENCE IF EXISTS custom_seq');
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    // ==================== knightColumns Tests ====================

    public function testKnightColumnsCreatesExpectedColumns(): void
    {
        Schema::create('knight_mixin_test', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightColumns();
        });

        $this->assertTrue(Schema::hasTable('knight_mixin_test'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'created_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'ukey'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'data_version'));
    }

    public function testKnightColumnsWithPostgres(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightColumns();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'created_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'updated_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'deleted_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'ukey'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'data_version'));

        // Verify data_version default value
        $connection->table('knight_mixin_test_pg')->insert(['name' => 'test']);
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertEquals(0, $record->data_version);
    }

    public function testKnightColumnsDataInsert(): void
    {
        Schema::create('knight_mixin_test', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightColumns();
        });

        // Insert data without specifying knight columns
        DB::table('knight_mixin_test')->insert(['name' => 'test']);

        $record = DB::table('knight_mixin_test')->first();
        $this->assertEquals('test', $record->name);
        $this->assertEquals(0, $record->data_version);
        $this->assertEquals('', $record->ukey);
        $this->assertNull($record->deleted_at);
    }

    // ==================== knightGin Index Tests ====================

    public function testKnightGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->knightGin('tags');
        });

        $this->assertTrue($schema->hasTable('knight_gin_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
        $this->assertEquals('idx_knight_gin_test_pg_tags_gin', $indexes[0]->indexname);
    }

    public function testKnightGinIndexWithCustomName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('metadata')->nullable();
            $table->knightGin('metadata', 'custom_gin_index');
        });

        // Verify custom index name
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname = 'custom_gin_index'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightGinIndexFunctionality(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->knightGin('tags');
        });

        // Insert test data
        $connection->table('knight_gin_test_pg')->insert([
            ['tags' => json_encode(['php', 'laravel'])],
            ['tags' => json_encode(['python', 'django'])],
            ['tags' => json_encode(['php', 'symfony'])],
        ]);

        // Query using GIN index
        $results = $connection->table('knight_gin_test_pg')
            ->whereRaw('tags @> ?', [json_encode(['php'])])
            ->get();

        $this->assertCount(2, $results);
    }

    // ==================== knightGin Multi-column Tests ====================

    public function testKnightGinIndexMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfBtreeGinExtensionNotAvailable();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_btree_test', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->jsonb('tags')->nullable();
            $table->knightGin(['tenant_id', 'tags']);
        });

        $this->assertTrue($schema->hasTable('knight_gin_btree_test'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_btree_test' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightGinIndexThreeColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfBtreeGinExtensionNotAvailable();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_btree_test', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->string('status');
            $table->jsonb('tags')->nullable();
            $table->knightGin(['tenant_id', 'status', 'tags']);
        });

        $this->assertTrue($schema->hasTable('knight_gin_btree_test'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_btree_test' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
    }

    // ==================== knightGinWhere Tests ====================

    public function testKnightGinWhere(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhere('tags', 'deleted_at IS NULL');
        });

        $this->assertTrue($schema->hasTable('knight_gin_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
        $this->assertEquals('idx_knight_gin_test_pg_tags_gin', $indexes[0]->indexname);

        // Verify index has WHERE clause
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname = 'idx_knight_gin_test_pg_tags_gin'"
        );
        $this->assertStringContainsString('WHERE', $indexDef->indexdef);
        $this->assertStringContainsString('deleted_at', $indexDef->indexdef);
    }

    public function testKnightGinWhereWithCustomName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhere('metadata', 'deleted_at IS NULL', 'custom_gin_where_index');
        });

        // Verify custom index name
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname = 'custom_gin_where_index'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightGinWhereMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfBtreeGinExtensionNotAvailable();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_btree_test', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhere(['tenant_id', 'tags'], 'deleted_at IS NULL');
        });

        $this->assertTrue($schema->hasTable('knight_gin_btree_test'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_btree_test' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);

        // Verify index has WHERE clause
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_gin_btree_test' AND indexname LIKE '%_gin'"
        );
        $this->assertStringContainsString('WHERE', $indexDef->indexdef);
    }

    public function testKnightGinWhereFunctionality(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhere('tags', 'deleted_at IS NULL');
        });

        // Insert test data
        $connection->table('knight_gin_test_pg')->insert([
            ['tags' => json_encode(['php', 'laravel']), 'deleted_at' => null],
            ['tags' => json_encode(['python', 'django']), 'deleted_at' => now()],
            ['tags' => json_encode(['php', 'symfony']), 'deleted_at' => null],
        ]);

        // Query using GIN index on non-deleted records
        $results = $connection->table('knight_gin_test_pg')
            ->whereNull('deleted_at')
            ->whereRaw('tags @> ?', [json_encode(['php'])])
            ->get();

        $this->assertCount(2, $results);
    }

    // ==================== knightGinWhereNotDeleted Tests ====================

    public function testKnightGinWhereNotDeleted(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhereNotDeleted('tags');
        });

        $this->assertTrue($schema->hasTable('knight_gin_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);

        // Verify index has WHERE clause with deleted_at IS NULL
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertStringContainsString('WHERE', $indexDef->indexdef);
        $this->assertStringContainsString('deleted_at', $indexDef->indexdef);
    }

    public function testKnightGinWhereNotDeletedMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfBtreeGinExtensionNotAvailable();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_btree_test', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhereNotDeleted(['tenant_id', 'tags']);
        });

        $this->assertTrue($schema->hasTable('knight_gin_btree_test'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_btree_test' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightGinWhereNotDeletedCustomDeletedAtColumn(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->knightGinWhereNotDeleted('tags', null, 'removed_at');
        });

        // Verify index uses custom deleted_at column
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertStringContainsString('removed_at', $indexDef->indexdef);
    }

    public function testKnightGinWhereNotDeletedWithCustomIndexName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_gin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhereNotDeleted('tags', 'my_custom_gin_index');
        });

        // Verify custom index name
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_gin_test_pg' AND indexname = 'my_custom_gin_index'"
        );
        $this->assertNotEmpty($indexes);
    }

    // ==================== knightUniqueWhere Tests ====================

    public function testKnightUniqueWhere(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhere('email', 'deleted_at IS NULL');
        });

        $this->assertTrue($schema->hasTable('knight_unique_where_test_pg'));

        // Verify unique index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_unique_where_test_pg' AND indexname LIKE 'uk_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightUniqueWhereEnforcesConstraint(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhere('email', 'deleted_at IS NULL');
        });

        // Insert first record
        $connection->table('knight_unique_where_test_pg')->insert(['email' => 'test@example.com']);

        // Attempt to insert duplicate - should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        $connection->table('knight_unique_where_test_pg')->insert(['email' => 'test@example.com']);
    }

    public function testKnightUniqueWhereAllowsDeletedDuplicates(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhere('email', 'deleted_at IS NULL');
        });

        // Insert first record
        $connection->table('knight_unique_where_test_pg')->insert(['email' => 'test@example.com']);

        // Soft delete it
        $connection->table('knight_unique_where_test_pg')
            ->where('email', 'test@example.com')
            ->update(['deleted_at' => now()]);

        // Insert same email - should succeed because first is deleted
        $connection->table('knight_unique_where_test_pg')->insert(['email' => 'test@example.com']);

        $count = $connection->table('knight_unique_where_test_pg')
            ->where('email', 'test@example.com')
            ->count();

        $this->assertEquals(2, $count);
    }

    public function testKnightUniqueWhereMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhere(['tenant_id', 'email'], 'deleted_at IS NULL');
        });

        // Insert records with same email but different tenant
        $connection->table('knight_unique_where_test_pg')->insert([
            ['tenant_id' => 1, 'email' => 'test@example.com'],
            ['tenant_id' => 2, 'email' => 'test@example.com'],
        ]);

        $count = $connection->table('knight_unique_where_test_pg')->count();
        $this->assertEquals(2, $count);
    }

    // ==================== knightIndexWhere Tests ====================

    public function testKnightIndexWhere(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_index_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->timestamp('deleted_at')->nullable();
            $table->knightIndexWhere('status', "status = 'active'");
        });

        $this->assertTrue($schema->hasTable('knight_index_where_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_index_where_test_pg' AND indexname LIKE 'idx_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightIndexWhereMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_index_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('status');
            $table->timestamp('deleted_at')->nullable();
            $table->knightIndexWhere(['user_id', 'status'], 'deleted_at IS NULL');
        });

        $this->assertTrue($schema->hasTable('knight_index_where_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_index_where_test_pg' AND indexname LIKE 'idx_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    // ==================== knightUniqueWhereNotDeleted Tests ====================

    public function testKnightUniqueWhereNotDeleted(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhereNotDeleted('email');
        });

        $this->assertTrue($schema->hasTable('knight_unique_where_test_pg'));

        // Verify unique index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_unique_where_test_pg' AND indexname LIKE 'uk_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightUniqueWhereNotDeletedMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhereNotDeleted(['tenant_id', 'email']);
        });

        $this->assertTrue($schema->hasTable('knight_unique_where_test_pg'));
    }

    public function testKnightUniqueWhereNotDeletedCustomDeletedAtColumn(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('removed_at')->nullable();
            $table->knightUniqueWhereNotDeleted('email', null, 'removed_at');
        });

        // Verify index uses custom deleted_at column
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_unique_where_test_pg' AND indexname LIKE 'uk_%'"
        );
        $this->assertStringContainsString('removed_at', $indexDef->indexdef);
    }

    // ==================== knightIndexWhereNotDeleted Tests ====================

    public function testKnightIndexWhereNotDeleted(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_index_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->timestamp('deleted_at')->nullable();
            $table->knightIndexWhereNotDeleted('user_id');
        });

        $this->assertTrue($schema->hasTable('knight_index_where_test_pg'));

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_index_where_test_pg' AND indexname LIKE 'idx_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testKnightIndexWhereNotDeletedMultipleColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_index_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('status');
            $table->timestamp('deleted_at')->nullable();
            $table->knightIndexWhereNotDeleted(['user_id', 'status']);
        });

        $this->assertTrue($schema->hasTable('knight_index_where_test_pg'));
    }

    // ==================== Schema Alter Table Tests ====================

    public function testAlterTableAddKnightGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create initial table
        $schema->create('knight_alter_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_alter_test_pg'));

        // Alter table to add GIN index
        $schema->table('knight_alter_test_pg', function (Blueprint $table) {
            $table->knightGin('tags');
        });

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_alter_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testAlterTableAddKnightUniqueWhere(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create initial table
        $schema->create('knight_alter_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_alter_test_pg'));

        // Alter table to add conditional unique index
        $schema->table('knight_alter_test_pg', function (Blueprint $table) {
            $table->knightUniqueWhere('email', 'deleted_at IS NULL');
        });

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_alter_test_pg' AND indexname LIKE 'uk_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testAlterTableAddKnightIndexWhere(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create initial table
        $schema->create('knight_alter_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('status');
        });

        $this->assertTrue($schema->hasTable('knight_alter_test_pg'));

        // Alter table to add conditional index
        $schema->table('knight_alter_test_pg', function (Blueprint $table) {
            $table->knightIndexWhere('status', "status = 'active'");
        });

        // Verify index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_alter_test_pg' AND indexname LIKE 'idx_%'"
        );
        $this->assertNotEmpty($indexes);
    }

    public function testAlterTableAddMultipleKnightIndexes(): void
    {
        $this->skipIfPgsqlNotConfigured();
        $this->skipIfBtreeGinExtensionNotAvailable();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create initial table
        $schema->create('knight_alter_test_pg', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->string('email');
            $table->jsonb('tags')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_alter_test_pg'));

        // Alter table to add multiple indexes
        $schema->table('knight_alter_test_pg', function (Blueprint $table) {
            $table->knightGin(['tenant_id', 'tags']);
            $table->knightUniqueWhereNotDeleted(['tenant_id', 'email']);
        });

        // Verify GIN index exists
        $ginIndexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_alter_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($ginIndexes);

        // Verify unique index exists
        $uniqueIndexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_alter_test_pg' AND indexname LIKE 'uk_%'"
        );
        $this->assertNotEmpty($uniqueIndexes);
    }

    public function testAlterTableAddColumnsAndIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create initial table
        $schema->create('knight_alter_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->assertTrue($schema->hasTable('knight_alter_test_pg'));

        // Alter table to add columns and knightColumns
        $schema->table('knight_alter_test_pg', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->knightColumns();
        });

        // Verify columns were added
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'email'));
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'created_at'));
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'updated_at'));
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'deleted_at'));
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'ukey'));
        $this->assertTrue($schema->hasColumn('knight_alter_test_pg', 'data_version'));
    }

    // ==================== PostgreSQL Array Column Tests ====================

    public function testKnightIntArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightIntArray('scores')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'scores'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (scores) VALUES (ARRAY[1, 2, 3]::integer[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightBigIntArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightBigIntArray('user_ids')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'user_ids'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (user_ids) VALUES (ARRAY[9223372036854775807]::bigint[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightSmallIntArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightSmallIntArray('ratings')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'ratings'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (ratings) VALUES (ARRAY[1, 2, 3]::smallint[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightTextArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightTextArray('tags')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'tags'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (tags) VALUES (ARRAY['php', 'laravel']::text[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightVarcharArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightVarcharArray('codes', 50)->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'codes'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (codes) VALUES (ARRAY['ABC', 'DEF']::varchar(50)[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightBooleanArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightBooleanArray('flags')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'flags'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (flags) VALUES (ARRAY[true, false]::boolean[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightDoubleArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightDoubleArray('prices')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'prices'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (prices) VALUES (ARRAY[1.5, 2.5]::double precision[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightFloatArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightFloatArray('coordinates')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'coordinates'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (coordinates) VALUES (ARRAY[1.5, 2.5]::real[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightUuidArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightUuidArray('related_ids')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'related_ids'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (related_ids) VALUES (ARRAY['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']::uuid[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightNumericArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightNumericArray('amounts', 10, 2)->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'amounts'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (amounts) VALUES (ARRAY[123.45, 678.90]::numeric(10,2)[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightTimestamptzArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightTimestamptzArray('event_times')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'event_times'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (event_times) VALUES (ARRAY['2024-01-01 00:00:00+00']::timestamptz[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightDateArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightDateArray('holidays')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'holidays'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (holidays) VALUES (ARRAY['2024-01-01', '2024-12-25']::date[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightJsonbArray(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightJsonbArray('metadata_list')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'metadata_list'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (metadata_list) VALUES (ARRAY['{\"key\": \"value\"}'::jsonb])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testMultipleArrayColumnsInOneTable(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightIntArray('scores')->nullable();
            $table->knightTextArray('tags')->nullable();
            $table->knightBigIntArray('user_ids')->nullable();
            $table->knightBooleanArray('flags')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'scores'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'tags'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'user_ids'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'flags'));
    }

    public function testArrayColumnWithGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightIntArray('scores')->nullable();
            $table->knightGin('scores');
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));

        // Verify GIN index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);
    }

    // ==================== knightColumnsReversed Tests ====================

    public function testKnightColumnsReversedCreatesExpectedColumns(): void
    {
        Schema::create('knight_mixin_test', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightColumnsReversed();
        });

        $this->assertTrue(Schema::hasTable('knight_mixin_test'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'created_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'ukey'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'data_version'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'options'));
        $this->assertTrue(Schema::hasColumn('knight_mixin_test', 'sort'));
    }

    public function testKnightColumnsReversedWithPostgres(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightColumnsReversed();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'created_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'updated_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'deleted_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'ukey'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'data_version'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'options'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'sort'));
    }

    // ==================== knightVarcharArray with Custom Length Tests ====================

    public function testKnightVarcharArrayWithCustomLength(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightVarcharArray('codes', 100)->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'codes'));

        // Insert and verify data with values up to 100 chars
        $longValue = str_repeat('A', 100);
        $connection->statement("INSERT INTO knight_mixin_test_pg (codes) VALUES (ARRAY['{$longValue}']::varchar(100)[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightVarcharArrayWithDefaultLength(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightVarcharArray('tags')->nullable(); // Default length 255
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'tags'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (tags) VALUES (ARRAY['tag1', 'tag2']::varchar(255)[])");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    // ==================== knightNumericArray with Precision/Scale Tests ====================

    public function testKnightNumericArrayWithPrecisionAndScale(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightNumericArray('amounts', 12, 4)->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'amounts'));

        // Insert and verify data with precision 12 and scale 4
        $connection->statement('INSERT INTO knight_mixin_test_pg (amounts) VALUES (ARRAY[12345678.1234, 87654321.4321]::numeric(12,4)[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightNumericArrayWithPrecisionOnly(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightNumericArray('values', 8)->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'values'));

        // Insert and verify data with precision 8
        $connection->statement('INSERT INTO knight_mixin_test_pg (values) VALUES (ARRAY[12345678, 87654321]::numeric(8)[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    public function testKnightNumericArrayWithoutParameters(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightNumericArray('numbers')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'numbers'));

        // Insert and verify data
        $connection->statement('INSERT INTO knight_mixin_test_pg (numbers) VALUES (ARRAY[123.456, 789.012]::numeric[])');
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
    }

    // ==================== Full-Text Search Column Tests ====================

    public function testKnightTsVector(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->knightTsVector('search_vector')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'search_vector'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (content, search_vector) VALUES ('hello world', to_tsvector('english', 'hello world'))");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->search_vector);
    }

    public function testKnightTsQuery(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->knightTsQuery('saved_query')->nullable();
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'saved_query'));

        // Insert and verify data
        $connection->statement("INSERT INTO knight_mixin_test_pg (saved_query) VALUES (to_tsquery('english', 'hello & world'))");
        $record = $connection->table('knight_mixin_test_pg')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->saved_query);
    }

    public function testKnightTsVectorWithGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->knightTsVector('search_vector')->nullable();
            $table->knightGin('search_vector');
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));

        // Verify GIN index exists
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotEmpty($indexes);

        // Insert and test full-text search with GIN index
        $connection->statement("INSERT INTO knight_mixin_test_pg (content, search_vector) VALUES ('PHP Laravel framework', to_tsvector('english', 'PHP Laravel framework'))");
        $connection->statement("INSERT INTO knight_mixin_test_pg (content, search_vector) VALUES ('Python Django web', to_tsvector('english', 'Python Django web'))");

        $results = $connection->select(
            "SELECT * FROM knight_mixin_test_pg WHERE search_vector @@ plainto_tsquery('english', 'Laravel')"
        );
        $this->assertCount(1, $results);
        $this->assertStringContainsString('Laravel', $results[0]->content);
    }

    public function testKnightTsVectorWithWhereGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->knightTsVector('search_vector')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->knightGinWhere('search_vector', 'deleted_at IS NULL');
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));

        // Verify GIN index exists with WHERE clause
        $indexDef = $connection->selectOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertNotNull($indexDef);
        $this->assertStringContainsString('WHERE', $indexDef->indexdef);
    }

    // ==================== Index Drop Tests ====================

    public function testDropGinIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create table with GIN index
        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->jsonb('tags')->nullable();
            $table->knightGin('tags', 'idx_tags_gin');
        });

        // Verify index exists
        $indexesBefore = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname = 'idx_tags_gin'"
        );
        $this->assertNotEmpty($indexesBefore);

        // Drop index manually
        $connection->statement('DROP INDEX IF EXISTS idx_tags_gin');

        // Verify index is dropped
        $indexesAfter = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname = 'idx_tags_gin'"
        );
        $this->assertEmpty($indexesAfter);
    }

    public function testDropConditionalUniqueIndex(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create table with conditional unique index
        $schema->create('knight_unique_where_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamp('deleted_at')->nullable();
            $table->knightUniqueWhere('email', 'deleted_at IS NULL', 'uk_email_active');
        });

        // Verify index exists
        $indexesBefore = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_unique_where_test_pg' AND indexname = 'uk_email_active'"
        );
        $this->assertNotEmpty($indexesBefore);

        // Drop index manually
        $connection->statement('DROP INDEX IF EXISTS uk_email_active');

        // Verify index is dropped
        $indexesAfter = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_unique_where_test_pg' AND indexname = 'uk_email_active'"
        );
        $this->assertEmpty($indexesAfter);
    }

    // ==================== Combined Features Tests ====================

    public function testFullTextSearchWithArrayColumns(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->knightTsVector('search_vector')->nullable();
            $table->knightTextArray('tags')->nullable();
            $table->knightGin('search_vector');
            $table->knightGin('tags', 'idx_tags_gin');
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'search_vector'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'tags'));

        // Verify both GIN indexes exist
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg' AND indexname LIKE '%_gin'"
        );
        $this->assertCount(2, $indexes);
    }

    public function testCompleteTableWithAllKnightFeatures(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_mixin_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->knightTsVector('search_vector')->nullable();
            $table->knightTextArray('tags')->nullable();
            $table->knightIntArray('scores')->nullable();
            $table->knightColumns();
            $table->knightGin('search_vector', 'idx_search_gin');
            $table->knightGinWhereNotDeleted('tags', 'idx_tags_gin');
            $table->knightUniqueWhereNotDeleted('name', 'uk_name');
        });

        $this->assertTrue($schema->hasTable('knight_mixin_test_pg'));

        // Verify all columns exist
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'name'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'description'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'search_vector'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'tags'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'scores'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'created_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'deleted_at'));
        $this->assertTrue($schema->hasColumn('knight_mixin_test_pg', 'data_version'));

        // Verify indexes exist
        $indexes = $connection->select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'knight_mixin_test_pg'"
        );
        $indexNames = array_map(fn ($i) => $i->indexname, $indexes);

        $this->assertContains('idx_search_gin', $indexNames);
        $this->assertContains('idx_tags_gin', $indexNames);
        $this->assertContains('uk_name', $indexNames);
    }

    // ==================== PostgreSQL Sequence Tests ====================

    public function testKnightSetSequenceValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create table with SERIAL primary key
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->assertTrue($schema->hasTable('knight_sequence_test_pg'));

        // Set sequence value using alter table
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 1000);
        });

        // Insert a record and verify the ID starts from 1000
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->orderByDesc('id')->first();

        $this->assertEquals(1000, $record->id);
    }

    public function testKnightSetSequenceValueMultipleInserts(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Set sequence to start from 5000
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 5000);
        });

        // Insert multiple records
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test3']);

        $records = $connection->table('knight_sequence_test_pg')->orderBy('id')->get();

        $this->assertEquals(5000, $records[0]->id);
        $this->assertEquals(5001, $records[1]->id);
        $this->assertEquals(5002, $records[2]->id);
    }

    public function testKnightSetSequenceValueWithCustomSequenceName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Set sequence value using custom sequence name
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 2000, 'knight_sequence_test_pg_id_seq');
        });

        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(2000, $record->id);
    }

    public function testKnightRestartSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // First insert some records
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);

        // Delete all records
        $connection->table('knight_sequence_test_pg')->truncate();

        // Restart sequence from 100
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightRestartSequence('id', 100);
        });

        // Insert new record
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test3']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(100, $record->id);
    }

    public function testKnightRestartSequenceMultipleInserts(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Restart sequence from 10000
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightRestartSequence('id', 10000);
        });

        // Insert multiple records
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test3']);

        $records = $connection->table('knight_sequence_test_pg')->orderBy('id')->get();

        $this->assertEquals(10000, $records[0]->id);
        $this->assertEquals(10001, $records[1]->id);
        $this->assertEquals(10002, $records[2]->id);
    }

    public function testKnightRestartSequenceWithCustomSequenceName(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Restart sequence with custom sequence name
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightRestartSequence('id', 3000, 'knight_sequence_test_pg_id_seq');
        });

        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(3000, $record->id);
    }

    public function testKnightSetSequenceValueDuringTableCreation(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create table and set sequence value in one migration
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightSetSequenceValue('id', 500);
        });

        $this->assertTrue($schema->hasTable('knight_sequence_test_pg'));

        // Insert a record and verify the ID
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(500, $record->id);
    }

    public function testKnightRestartSequenceDuringTableCreation(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create table and restart sequence in one migration
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightRestartSequence('id', 999);
        });

        $this->assertTrue($schema->hasTable('knight_sequence_test_pg'));

        // Insert a record and verify the ID
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(999, $record->id);
    }

    public function testKnightSetSequenceValueWithLargeNumber(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Set sequence to a large number
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 9000000000);
        });

        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(9000000000, $record->id);
    }

    public function testKnightSetSequenceValuePreservesExistingRecords(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Insert some records first
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);

        // Now set sequence to a higher value
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 1000);
        });

        // Insert another record
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test3']);

        // Verify all records exist with correct IDs
        $records = $connection->table('knight_sequence_test_pg')->orderBy('id')->get();

        $this->assertCount(3, $records);
        $this->assertEquals(1, $records[0]->id);
        $this->assertEquals(2, $records[1]->id);
        $this->assertEquals(1000, $records[2]->id);
    }

    public function testKnightSequenceMethodsChaining(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Test that methods return $this for chaining
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightSetSequenceValue('id', 100)
                  ->knightColumns();
        });

        $this->assertTrue($schema->hasTable('knight_sequence_test_pg'));
        $this->assertTrue($schema->hasColumn('knight_sequence_test_pg', 'created_at'));
        $this->assertTrue($schema->hasColumn('knight_sequence_test_pg', 'data_version'));
    }

    public function testSequenceValueDirectQuery(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->knightSetSequenceValue('id', 7777);
        });

        // Verify the sequence value using direct query
        $result = $connection->selectOne(
            'SELECT last_value, is_called FROM knight_sequence_test_pg_id_seq'
        );

        // After setval with false, last_value should be 7777 and is_called should be false
        $this->assertEquals(7777, $result->last_value);
        // is_called is false means next nextval() will return last_value
        $this->assertFalse($result->is_called);
    }

    // ==================== PostgreSQL Shared Sequence Tests ====================

    public function testKnightCreateSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence using a dummy table context
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1000);
        });

        // Verify sequence exists
        $result = $connection->selectOne(
            "SELECT sequencename FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNotNull($result);
        $this->assertEquals('global_test_seq', $result->sequencename);
    }

    public function testKnightCreateSequenceWithAllOptions(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 100, 2, 10000, 1, false);
        });

        // Verify sequence exists and has correct settings
        $result = $connection->selectOne(
            "SELECT start_value, increment_by, min_value, max_value, cycle
             FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNotNull($result);
        $this->assertEquals(100, $result->start_value);
        $this->assertEquals(2, $result->increment_by);
        $this->assertEquals(1, $result->min_value);
        $this->assertEquals(10000, $result->max_value);
        $this->assertFalse($result->cycle);
    }

    public function testKnightDropSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // First create a sequence
        $connection->statement('CREATE SEQUENCE global_test_seq START WITH 1');

        // Verify sequence exists
        $result = $connection->selectOne(
            "SELECT sequencename FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNotNull($result);

        // Drop the sequence
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightDropSequence('global_test_seq');
        });

        // Verify sequence no longer exists
        $result = $connection->selectOne(
            "SELECT sequencename FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNull($result);
    }

    public function testKnightDropSequenceIfExists(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a dummy table for context
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
        });

        // Drop sequence that doesn't exist (should not throw error)
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightDropSequence('non_existent_seq', true);
        });

        $this->assertTrue(true); // Test passes if no exception
    }

    public function testKnightIdWithSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a shared sequence
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 5000');

        // Create table using the shared sequence
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // Insert and verify
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'test1']);
        $record = $connection->table('knight_shared_seq_table1')->first();

        $this->assertEquals(5000, $record->id);
    }

    public function testKnightIdWithSequenceNonPrimary(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a shared sequence
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 1000');

        // Create table with regular id and secondary column using sequence
        $schema->create('knight_shared_seq_table1', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('external_id', 'shared_id_seq', false);
        });

        // Insert and verify
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'test1']);
        $record = $connection->table('knight_shared_seq_table1')->first();

        $this->assertEquals(1, $record->id);
        $this->assertEquals(1000, $record->external_id);
    }

    public function testMultipleTablesWithSharedSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a shared sequence starting at 10000
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 10000');

        // Create first table using the shared sequence
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // Create second table using the same sequence
        $connection->statement('CREATE TABLE knight_shared_seq_table2 (title VARCHAR(255))');
        $schema->table('knight_shared_seq_table2', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // Insert into first table
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'record1']);

        // Insert into second table
        $connection->table('knight_shared_seq_table2')->insert(['title' => 'record2']);

        // Insert into first table again
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'record3']);

        // Verify IDs are sequential across tables
        $record1 = $connection->table('knight_shared_seq_table1')->where('name', 'record1')->first();
        $record2 = $connection->table('knight_shared_seq_table2')->where('title', 'record2')->first();
        $record3 = $connection->table('knight_shared_seq_table1')->where('name', 'record3')->first();

        $this->assertEquals(10000, $record1->id);
        $this->assertEquals(10001, $record2->id);
        $this->assertEquals(10002, $record3->id);
    }

    public function testThreeTablesWithSharedSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a shared sequence
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 1');

        // Create three tables all using the same sequence
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        $connection->statement('CREATE TABLE knight_shared_seq_table2 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table2', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        $connection->statement('CREATE TABLE knight_shared_seq_table3 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table3', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // Insert in alternating order
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'A']);
        $connection->table('knight_shared_seq_table2')->insert(['name' => 'B']);
        $connection->table('knight_shared_seq_table3')->insert(['name' => 'C']);
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'D']);
        $connection->table('knight_shared_seq_table2')->insert(['name' => 'E']);
        $connection->table('knight_shared_seq_table3')->insert(['name' => 'F']);

        // Collect all IDs
        $ids = [];
        foreach (['knight_shared_seq_table1', 'knight_shared_seq_table2', 'knight_shared_seq_table3'] as $table) {
            $records = $connection->table($table)->get();
            foreach ($records as $record) {
                $ids[] = $record->id;
            }
        }

        sort($ids);

        // All IDs should be unique and sequential
        $this->assertEquals([1, 2, 3, 4, 5, 6], $ids);
    }

    public function testKnightUseSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence
        $connection->statement('CREATE SEQUENCE custom_seq START WITH 8000');

        // Create table with standard id
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Modify id column to use custom sequence
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightUseSequence('id', 'custom_seq');
        });

        // Insert and verify
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        $this->assertEquals(8000, $record->id);
    }

    public function testKnightUseSequenceMultipleInserts(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence
        $connection->statement('CREATE SEQUENCE custom_seq START WITH 100 INCREMENT BY 5');

        // Create table
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Modify to use custom sequence
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightUseSequence('id', 'custom_seq');
        });

        // Insert multiple records
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test3']);

        $records = $connection->table('knight_sequence_test_pg')->orderBy('id')->get();

        // With INCREMENT BY 5, IDs should be 100, 105, 110
        $this->assertEquals(100, $records[0]->id);
        $this->assertEquals(105, $records[1]->id);
        $this->assertEquals(110, $records[2]->id);
    }

    public function testCreateSequenceAndUseTogether(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create sequence in one migration
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 50000);
        });

        // Create first table
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'global_test_seq');
        });

        // Create second table
        $connection->statement('CREATE TABLE knight_shared_seq_table2 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table2', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'global_test_seq');
        });

        // Insert and verify
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'A']);
        $connection->table('knight_shared_seq_table2')->insert(['name' => 'B']);

        $r1 = $connection->table('knight_shared_seq_table1')->first();
        $r2 = $connection->table('knight_shared_seq_table2')->first();

        $this->assertEquals(50000, $r1->id);
        $this->assertEquals(50001, $r2->id);
    }

    public function testSequenceCycleOption(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence with cycle and small max
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1, 1, 3, 1, true);
        });

        // Get values and verify cycling
        $val1 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val2 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val3 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val4 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;

        $this->assertEquals(1, $val1);
        $this->assertEquals(2, $val2);
        $this->assertEquals(3, $val3);
        $this->assertEquals(1, $val4); // Should cycle back to 1
    }

    public function testSequenceCacheOption(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence with cache = 20
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1, 1, null, 1, false, 20);
        });

        // Verify sequence exists with correct cache setting
        $result = $connection->selectOne(
            "SELECT cache_size FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNotNull($result);
        $this->assertEquals(20, $result->cache_size);
    }

    public function testSequenceCacheDefaultValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence without specifying cache (default = 1)
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1000);
        });

        // Verify sequence has cache = 1
        $result = $connection->selectOne(
            "SELECT cache_size FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->cache_size);
    }

    public function testSequenceWithAllOptions(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // Create a sequence with all options
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence(
                'global_test_seq',
                100,      // startWith
                5,        // incrementBy
                1000,     // maxValue
                1,        // minValue
                true,     // cycle
                50        // cache
            );
        });

        // Verify all settings
        $result = $connection->selectOne(
            "SELECT start_value, increment_by, min_value, max_value, cycle, cache_size
             FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );

        $this->assertEquals(100, $result->start_value);
        $this->assertEquals(5, $result->increment_by);
        $this->assertEquals(1, $result->min_value);
        $this->assertEquals(1000, $result->max_value);
        $this->assertTrue($result->cycle);
        $this->assertEquals(50, $result->cache_size);
    }

    // ==================== Strict Additional Tests ====================

    public function testKnightSetSequenceValueWithZero(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 设置序列值为 0 应该会导致错误或特殊行为
        // PostgreSQL 序列最小值通常是 1
        $this->expectException(\Throwable::class);

        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 0);
        });
    }

    public function testKnightSetSequenceValueWithNegative(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 设置序列为负数应该会报错（序列默认 MINVALUE 是 1）
        $this->expectException(\Throwable::class);

        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', -100);
        });
    }

    public function testKnightCreateSequenceWithNegativeIncrement(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 创建递减序列
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 100, -1, 100, -1000, false);
        });

        // 验证序列存在
        $result = $connection->selectOne(
            "SELECT start_value, increment_by, min_value, max_value
             FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );

        $this->assertEquals(100, $result->start_value);
        $this->assertEquals(-1, $result->increment_by);
        $this->assertEquals(-1000, $result->min_value);
        $this->assertEquals(100, $result->max_value);

        // 验证递减行为
        $val1 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val2 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val3 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;

        $this->assertEquals(100, $val1);
        $this->assertEquals(99, $val2);
        $this->assertEquals(98, $val3);
    }

    public function testKnightCreateSequenceWithZeroCache(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // cache = 0 应该被转换为 1
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1, 1, null, 1, false, 0);
        });

        $result = $connection->selectOne(
            "SELECT cache_size FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );

        // 由于 max(1, 0) = 1，cache 应该是 1
        $this->assertEquals(1, $result->cache_size);
    }

    public function testKnightCreateSequenceWithNegativeCache(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 负数 cache 应该被转换为 1
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1, 1, null, 1, false, -10);
        });

        $result = $connection->selectOne(
            "SELECT cache_size FROM pg_sequences WHERE sequencename = 'global_test_seq'"
        );

        // 由于 max(1, -10) = 1，cache 应该是 1
        $this->assertEquals(1, $result->cache_size);
    }

    public function testKnightIdWithSequenceColumnProperties(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 创建共享序列
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 1');

        // 创建表
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // 验证列属性
        $column = $connection->selectOne("
            SELECT column_name, data_type, column_default, is_nullable
            FROM information_schema.columns
            WHERE table_name = 'knight_shared_seq_table1' AND column_name = 'id'
        ");

        $this->assertEquals('id', $column->column_name);
        $this->assertEquals('bigint', $column->data_type);
        $this->assertStringContainsString('shared_id_seq', $column->column_default);
        $this->assertEquals('NO', $column->is_nullable);

        // 验证是否为主键
        $pk = $connection->selectOne("
            SELECT constraint_name
            FROM information_schema.table_constraints
            WHERE table_name = 'knight_shared_seq_table1' AND constraint_type = 'PRIMARY KEY'
        ");

        $this->assertNotNull($pk);
    }

    public function testKnightRestartSequencePreservesSequenceProperties(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 获取序列属性
        $before = $connection->selectOne(
            "SELECT increment_by FROM pg_sequences WHERE sequencename = 'knight_sequence_test_pg_id_seq'"
        );

        // 重启序列
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightRestartSequence('id', 5000);
        });

        // 验证序列属性未改变（只有值改变了）
        $after = $connection->selectOne(
            "SELECT increment_by FROM pg_sequences WHERE sequencename = 'knight_sequence_test_pg_id_seq'"
        );

        $this->assertEquals($before->increment_by, $after->increment_by);

        // 验证值确实改变了
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test']);
        $record = $connection->table('knight_sequence_test_pg')->first();
        $this->assertEquals(5000, $record->id);
    }

    public function testKnightDropSequenceFailsForNonExistent(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
        });

        // 删除不存在的序列（没有 IF EXISTS）应该抛出异常
        $this->expectException(\Throwable::class);

        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightDropSequence('absolutely_nonexistent_seq_xyz_123', false);
        });
    }

    public function testKnightUseSequenceOnNonExistentSequence(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 使用不存在的序列应该失败
        $this->expectException(\Throwable::class);

        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightUseSequence('id', 'nonexistent_sequence_xyz');
        });
    }

    public function testSequenceValueBoundaryBigInt(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 设置一个接近 BIGINT 最大值的值（但不是最大值，避免溢出）
        $largeValue = 9223372036854775000;  // 接近 PHP_INT_MAX

        $schema->table('knight_sequence_test_pg', function (Blueprint $table) use ($largeValue) {
            $table->knightSetSequenceValue('id', $largeValue);
        });

        // 插入记录并验证
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);
        $record = $connection->table('knight_sequence_test_pg')->first();

        // PostgreSQL 返回的是字符串（因为数字太大）
        $this->assertEquals((string) $largeValue, (string) $record->id);
    }

    public function testMultipleSequenceOperationsInSameTable(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 在同一个 table 回调中执行多个序列操作
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 创建表的同时设置序列值
            $table->knightSetSequenceValue('id', 100);
        });

        // 插入第一条记录
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test1']);

        // 在同一个 table 回调中修改序列值两次
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 200);
            $table->knightSetSequenceValue('id', 300);
        });

        // 插入第二条记录 - 应该使用最后设置的值 300
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test2']);

        $records = $connection->table('knight_sequence_test_pg')->orderBy('id')->get();

        $this->assertEquals(100, $records[0]->id);
        $this->assertEquals(300, $records[1]->id);
    }

    public function testSharedSequenceAfterDrop(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 创建共享序列
        $connection->statement('CREATE SEQUENCE shared_id_seq START WITH 1000');

        // 创建使用该序列的表
        $connection->statement('CREATE TABLE knight_shared_seq_table1 (name VARCHAR(255))');
        $schema->table('knight_shared_seq_table1', function (Blueprint $table) {
            $table->knightIdWithSequence('id', 'shared_id_seq');
        });

        // 插入一条记录
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'test1']);
        $record1 = $connection->table('knight_shared_seq_table1')->first();
        $this->assertEquals(1000, $record1->id);

        // 删除序列（CASCADE 会同时删除依赖）
        $connection->statement('DROP SEQUENCE shared_id_seq CASCADE');

        // 现在插入应该失败
        $this->expectException(\Throwable::class);
        $connection->table('knight_shared_seq_table1')->insert(['name' => 'test2']);
    }

    public function testSequenceIncrementByLargeValue(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        // 创建大步长的序列
        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightCreateSequence('global_test_seq', 1, 1000, null, 1, false);
        });

        $val1 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val2 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;
        $val3 = $connection->selectOne("SELECT nextval('global_test_seq') as val")->val;

        $this->assertEquals(1, $val1);
        $this->assertEquals(1001, $val2);
        $this->assertEquals(2001, $val3);
    }

    public function testKnightSetSequenceValueVerifyIsCalled(): void
    {
        $this->skipIfPgsqlNotConfigured();

        $connection = DB::connection('pgsql');
        $schema = $connection->getSchemaBuilder();

        $schema->create('knight_sequence_test_pg', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // 使用 setval(..., false) 设置值
        $schema->table('knight_sequence_test_pg', function (Blueprint $table) {
            $table->knightSetSequenceValue('id', 500);
        });

        // 验证 is_called 为 false（下一次 nextval 返回设置的值）
        $result = $connection->selectOne(
            'SELECT last_value, is_called FROM knight_sequence_test_pg_id_seq'
        );

        $this->assertEquals(500, $result->last_value);
        $this->assertFalse($result->is_called);

        // 插入记录应该得到 500
        $connection->table('knight_sequence_test_pg')->insert(['name' => 'test']);
        $record = $connection->table('knight_sequence_test_pg')->first();
        $this->assertEquals(500, $record->id);

        // 再次检查 is_called
        $result2 = $connection->selectOne(
            'SELECT last_value, is_called FROM knight_sequence_test_pg_id_seq'
        );
        $this->assertTrue($result2->is_called);
    }

    // ==================== Helper Methods ====================

    protected function skipIfBtreeGinExtensionNotAvailable(): void
    {
        if (! $this->isPgsqlConfigured()) {
            return;
        }

        try {
            $connection = DB::connection('pgsql');
            $result = $connection->selectOne(
                "SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'btree_gin') as exists"
            );

            if (! $result->exists) {
                // Try to create extension
                try {
                    $connection->statement('CREATE EXTENSION IF NOT EXISTS btree_gin');
                } catch (\Throwable $e) {
                    $this->markTestSkipped('btree_gin extension is not available: '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not check btree_gin extension: '.$e->getMessage());
        }
    }
}
