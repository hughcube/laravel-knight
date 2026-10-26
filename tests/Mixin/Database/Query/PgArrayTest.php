<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/1/16
 * Time: 10:00.
 */

namespace HughCube\Laravel\Knight\Tests\Mixin\Database\Query;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL Array Query Tests.
 *
 * Tests for type-specific array methods like whereIntArrayContains,
 * whereTextArrayContains, whereBigIntArrayContains, etc.
 */
class PgArrayTest extends TestCase
{
    protected static bool $arrayTablePrepared = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isPgsqlConfigured() || self::$arrayTablePrepared) {
            return;
        }

        $this->setUpArrayTable();
        self::$arrayTablePrepared = true;
    }

    protected function getTestQuery(bool $requiresConnection = true)
    {
        if ($requiresConnection) {
            $this->skipIfPgsqlNotConfigured();

            return DB::connection('pgsql')->table('knight_array_test');
        }

        return $this->getSqlOnlyQuery();
    }

    protected function getSqlOnlyQuery()
    {
        $connection = new PostgresConnection(
            function () {
                throw new \RuntimeException('PDO connection is not available for SQL-only tests.');
            },
            '',
            '',
            ['driver' => 'pgsql']
        );

        return $connection->table('knight_array_test');
    }

    protected function parsePgArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '{}' || $value === '') {
            return [];
        }

        $items = str_getcsv(substr($value, 1, -1), ',', '"', '\\');

        return array_map(function ($item) {
            return preg_replace('/\\\\(.)/', '$1', $item);
        }, $items);
    }

    protected function setUpArrayTable(): void
    {
        $connection = DB::connection('pgsql');

        $connection->statement('DROP TABLE IF EXISTS knight_array_test');
        $connection->statement(
            'CREATE TABLE knight_array_test (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                tags TEXT[] NOT NULL,
                scores INTEGER[] NOT NULL,
                prices DOUBLE PRECISION[] NOT NULL,
                flags BOOLEAN[] NOT NULL
            )'
        );

        foreach ($this->seedRows() as $row) {
            $this->insertRow($connection, $row);
        }
    }

    protected function seedRows(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'record1',
                'tags' => ['php', 'laravel', 'mysql'],
                'scores' => [80, 90],
                'prices' => [1.5, 2.5],
                'flags' => ['true', 'false'],
            ],
            [
                'id' => 2,
                'name' => 'record2',
                'tags' => ['java', 'spring'],
                'scores' => [95, 100],
                'prices' => [3.5],
                'flags' => ['true'],
            ],
            [
                'id' => 3,
                'name' => 'record3',
                'tags' => ['php', 'symfony', 'django'],
                'scores' => [70, 85],
                'prices' => [4.0],
                'flags' => ['false'],
            ],
            [
                'id' => 4,
                'name' => 'record4',
                'tags' => ['php', 'laravel'],
                'scores' => [1, 2, 3],
                'prices' => [1.0, 2.0],
                'flags' => ['true', 'false'],
            ],
        ];
    }

    protected function insertRow($connection, array $row): void
    {
        [$tagsSql, $tagsBindings] = $this->buildArraySql($row['tags']);
        [$scoresSql, $scoresBindings] = $this->buildArraySql($row['scores']);
        [$pricesSql, $pricesBindings] = $this->buildArraySql($row['prices']);
        [$flagsSql, $flagsBindings] = $this->buildArraySql($row['flags']);

        $sql = sprintf(
            'INSERT INTO knight_array_test (id, name, tags, scores, prices, flags)
             VALUES (?, ?, %s::text[], %s::integer[], %s::double precision[], %s::boolean[])',
            $tagsSql,
            $scoresSql,
            $pricesSql,
            $flagsSql
        );

        $bindings = array_merge(
            [$row['id'], $row['name']],
            $tagsBindings,
            $scoresBindings,
            $pricesBindings,
            $flagsBindings
        );

        $connection->statement($sql, $bindings);
    }

    protected function buildArraySql(array $values): array
    {
        if ($values === []) {
            return ['ARRAY[]', []];
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return ['ARRAY['.$placeholders.']', array_values($values)];
    }

    // ==================== INTEGER Array Methods ====================

    public function testWhereIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereIntArrayContains('scores', [80, 90]);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::integer[]', $sql);
        $this->assertEquals([80, 90], $bindings);
    }

    public function testOrWhereIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereIntArrayContains('scores', [80]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::integer[]', $sql);
    }

    public function testWhereNotIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotIntArrayContains('scores', [80]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::integer[]', $sql);
    }

    public function testOrWhereNotIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotIntArrayContains('scores', [80]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::integer[]', $sql);
    }

    public function testWhereIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereIntArrayContainedBy('scores', [80, 90, 100]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::integer[]', $sql);
    }

    public function testOrWhereIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereIntArrayContainedBy('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotIntArrayContainedBy('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotIntArrayContainedBy('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereIntArrayOverlaps('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::integer[]', $sql);
    }

    public function testOrWhereIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereIntArrayOverlaps('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotIntArrayOverlaps('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotIntArrayOverlaps('scores', [80, 90]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // ==================== BIGINT Array Methods ====================

    public function testWhereBigIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereBigIntArrayContains('user_ids', [9223372036854775807]);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?]::bigint[]', $sql);
    }

    public function testOrWhereBigIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBigIntArrayContains('user_ids', [123]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::bigint[]', $sql);
    }

    public function testWhereBigIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereBigIntArrayContainedBy('user_ids', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::bigint[]', $sql);
    }

    public function testWhereBigIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereBigIntArrayOverlaps('user_ids', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::bigint[]', $sql);
    }

    // ==================== SMALLINT Array Methods ====================

    public function testWhereSmallIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereSmallIntArrayContains('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::smallint[]', $sql);
    }

    public function testWhereSmallIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereSmallIntArrayContainedBy('ratings', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::smallint[]', $sql);
    }

    public function testWhereSmallIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereSmallIntArrayOverlaps('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::smallint[]', $sql);
    }

    // ==================== TEXT Array Methods ====================

    public function testWhereTextArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayContains('tags', ['php', 'laravel']);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::text[]', $sql);
    }

    public function testOrWhereTextArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereTextArrayContains('tags', ['php']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::text[]', $sql);
    }

    public function testWhereNotTextArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotTextArrayContains('tags', ['php']);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::text[]', $sql);
    }

    public function testWhereTextArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayContainedBy('tags', ['php', 'laravel', 'mysql']);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::text[]', $sql);
    }

    public function testWhereTextArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayOverlaps('tags', ['php', 'java']);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::text[]', $sql);
    }

    // ==================== BOOLEAN Array Methods ====================

    public function testWhereBooleanArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereBooleanArrayContains('flags', [true, false]);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::boolean[]', $sql);
    }

    public function testWhereBooleanArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereBooleanArrayContainedBy('flags', [true, false]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::boolean[]', $sql);
    }

    public function testWhereBooleanArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereBooleanArrayOverlaps('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?]::boolean[]', $sql);
    }

    // ==================== DOUBLE PRECISION Array Methods ====================

    public function testWhereDoubleArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereDoubleArrayContains('prices', [1.5, 2.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::double precision[]', $sql);
    }

    public function testWhereDoubleArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereDoubleArrayContainedBy('prices', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::double precision[]', $sql);
    }

    public function testWhereDoubleArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereDoubleArrayOverlaps('prices', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::double precision[]', $sql);
    }

    // ==================== REAL (Float) Array Methods ====================

    public function testWhereFloatArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereFloatArrayContains('coordinates', [1.5, 2.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::real[]', $sql);
    }

    public function testWhereFloatArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereFloatArrayContainedBy('coordinates', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?, ?]::real[]', $sql);
    }

    public function testWhereFloatArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereFloatArrayOverlaps('coordinates', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::real[]', $sql);
    }

    // ==================== UUID Array Methods ====================

    public function testWhereUuidArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereUuidArrayContains('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('ARRAY[?]::uuid[]', $sql);
    }

    public function testOrWhereUuidArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereUuidArrayContains('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::uuid[]', $sql);
    }

    public function testWhereUuidArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereUuidArrayContainedBy('related_ids', [
                'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'b1ffcd00-0d1c-5fg9-cc7e-7cc0ce491b22',
            ]);

        $sql = $query->toSql();

        $this->assertStringContainsString('<@', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::uuid[]', $sql);
    }

    public function testWhereUuidArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereUuidArrayOverlaps('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('&&', $sql);
        $this->assertStringContainsString('ARRAY[?]::uuid[]', $sql);
    }

    // ==================== Mixed type method tests ====================

    public function testMixedTypeSpecificMethods()
    {
        $query = $this->getTestQuery(false)
            ->whereIntArrayContains('scores', [80])
            ->whereTextArrayOverlaps('tags', ['php', 'laravel']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('ARRAY[?]::integer[]', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::text[]', $sql);
        $this->assertEquals([80, 'php', 'laravel'], $bindings);
    }

    public function testTypeSpecificWithOtherConditions()
    {
        $query = $this->getTestQuery(false)
            ->where('id', '>', 2)
            ->whereIntArrayOverlaps('scores', [80, 90])
            ->whereTextArrayContains('tags', ['php']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('"id" > ?', $sql);
        $this->assertStringContainsString('ARRAY[?, ?]::integer[]', $sql);
        $this->assertStringContainsString('ARRAY[?]::text[]', $sql);
        $this->assertEquals([2, 80, 90, 'php'], $bindings);
    }

    // ==================== Empty array tests ====================

    public function testEmptyIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereIntArrayContains('scores', []);

        $sql = $query->toSql();

        $this->assertStringContainsString('ARRAY[]::integer[]', $sql);
    }

    public function testEmptyTextArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayOverlaps('tags', []);

        $sql = $query->toSql();

        $this->assertStringContainsString('ARRAY[]::text[]', $sql);
    }

    // ==================== SQL Injection prevention tests ====================

    public function testSqlInjectionPreventionWithTextArray()
    {
        $maliciousInput = "test'; DROP TABLE users; --";

        $query = $this->getTestQuery(false)
            ->whereTextArrayContains('tags', [$maliciousInput]);

        $bindings = $query->getBindings();

        // Value should be properly bound
        $this->assertCount(1, $bindings);
        $this->assertEquals($maliciousInput, $bindings[0]);
    }

    public function testBindingsAreProperlyEscaped()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayContains('tags', ["test'; DROP TABLE users; --", 'normal']);

        $bindings = $query->getBindings();

        // Each value should be a separate binding
        $this->assertCount(2, $bindings);
        $this->assertEquals("test'; DROP TABLE users; --", $bindings[0]);
        $this->assertEquals('normal', $bindings[1]);
    }

    // ==================== Real database execution tests ====================

    public function testIntArrayMethodsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereIntArrayContains('scores', [80, 90])
                ->get()
        );

        $results->each(function ($item) {
            $scores = $this->parsePgArray($item->scores);
            $this->assertTrue(in_array('80', $scores));
            $this->assertTrue(in_array('90', $scores));
        });
    }

    public function testTextArrayMethodsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereTextArrayContains('tags', ['php', 'laravel'])
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertTrue(in_array('php', $tags));
            $this->assertTrue(in_array('laravel', $tags));
        });
    }

    public function testIntArrayOverlapsWithRealDatabase()
    {
        $overlaps = ['95', '100'];
        $results = Collection::make(
            $this->getTestQuery()
                ->whereIntArrayOverlaps('scores', [95, 100])
                ->get()
        );

        $results->each(function ($item) use ($overlaps) {
            $scores = $this->parsePgArray($item->scores);
            $this->assertNotEmpty(array_intersect($scores, $overlaps));
        });
    }

    public function testTextArrayContainedByWithRealDatabase()
    {
        $allowed = ['php', 'laravel'];
        $results = Collection::make(
            $this->getTestQuery()
                ->whereTextArrayContainedBy('tags', $allowed)
                ->get()
        );

        $results->each(function ($item) use ($allowed) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertSame([], array_diff($tags, $allowed));
        });
    }

    public function testOrWhereTextArrayContainsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->where('name', 'record3')
                ->orWhereTextArrayContains('tags', ['symfony'])
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertTrue($item->name === 'record3' || in_array('symfony', $tags));
        });
    }

    public function testWhereNotTextArrayContainsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereNotTextArrayContains('tags', ['php'])
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertFalse(in_array('php', $tags));
        });
    }

    public function testCombinedArrayQueriesWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereTextArrayContains('tags', ['php'])
                ->whereTextArrayOverlaps('tags', ['laravel'])
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertTrue(in_array('php', $tags));
            $this->assertTrue(in_array('laravel', $tags));
        });
    }

    public function testArrayWithOtherConditionsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->where('id', '>', 2)
                ->whereTextArrayOverlaps('tags', ['php'])
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertGreaterThan(2, $item->id);
            $this->assertTrue(in_array('php', $tags));
        });
    }

    // ==================== Additional variant method tests ====================

    // BigInt variants
    public function testWhereNotBigIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBigIntArrayContains('user_ids', [123]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::bigint[]', $sql);
    }

    public function testOrWhereNotBigIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBigIntArrayContains('user_ids', [123]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::bigint[]', $sql);
    }

    public function testOrWhereBigIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBigIntArrayContainedBy('user_ids', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotBigIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBigIntArrayContainedBy('user_ids', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotBigIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBigIntArrayContainedBy('user_ids', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereBigIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBigIntArrayOverlaps('user_ids', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotBigIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBigIntArrayOverlaps('user_ids', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotBigIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBigIntArrayOverlaps('user_ids', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // SmallInt variants
    public function testOrWhereSmallIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereSmallIntArrayContains('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?, ?]::smallint[]', $sql);
    }

    public function testWhereNotSmallIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotSmallIntArrayContains('ratings', [1]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::smallint[]', $sql);
    }

    public function testOrWhereNotSmallIntArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotSmallIntArrayContains('ratings', [1]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::smallint[]', $sql);
    }

    public function testOrWhereSmallIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereSmallIntArrayContainedBy('ratings', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotSmallIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotSmallIntArrayContainedBy('ratings', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotSmallIntArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotSmallIntArrayContainedBy('ratings', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereSmallIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereSmallIntArrayOverlaps('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotSmallIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotSmallIntArrayOverlaps('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotSmallIntArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotSmallIntArrayOverlaps('ratings', [1, 2]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // Text variants
    public function testOrWhereNotTextArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotTextArrayContains('tags', ['php']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::text[]', $sql);
    }

    public function testOrWhereTextArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereTextArrayContainedBy('tags', ['php', 'laravel', 'mysql']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotTextArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotTextArrayContainedBy('tags', ['php', 'laravel', 'mysql']);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotTextArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotTextArrayContainedBy('tags', ['php', 'laravel', 'mysql']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereTextArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereTextArrayOverlaps('tags', ['php', 'java']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotTextArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotTextArrayOverlaps('tags', ['php', 'java']);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotTextArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotTextArrayOverlaps('tags', ['php', 'java']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // Boolean variants
    public function testOrWhereBooleanArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBooleanArrayContains('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::boolean[]', $sql);
    }

    public function testWhereNotBooleanArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBooleanArrayContains('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::boolean[]', $sql);
    }

    public function testOrWhereNotBooleanArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBooleanArrayContains('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::boolean[]', $sql);
    }

    public function testOrWhereBooleanArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBooleanArrayContainedBy('flags', [true, false]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotBooleanArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBooleanArrayContainedBy('flags', [true, false]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotBooleanArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBooleanArrayContainedBy('flags', [true, false]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereBooleanArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereBooleanArrayOverlaps('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotBooleanArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotBooleanArrayOverlaps('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotBooleanArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotBooleanArrayOverlaps('flags', [true]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // Double variants
    public function testOrWhereDoubleArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereDoubleArrayContains('prices', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::double precision[]', $sql);
    }

    public function testWhereNotDoubleArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotDoubleArrayContains('prices', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::double precision[]', $sql);
    }

    public function testOrWhereNotDoubleArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotDoubleArrayContains('prices', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::double precision[]', $sql);
    }

    public function testOrWhereDoubleArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereDoubleArrayContainedBy('prices', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotDoubleArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotDoubleArrayContainedBy('prices', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotDoubleArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotDoubleArrayContainedBy('prices', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereDoubleArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereDoubleArrayOverlaps('prices', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotDoubleArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotDoubleArrayOverlaps('prices', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotDoubleArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotDoubleArrayOverlaps('prices', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // Float variants
    public function testOrWhereFloatArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereFloatArrayContains('coordinates', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::real[]', $sql);
    }

    public function testWhereNotFloatArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotFloatArrayContains('coordinates', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::real[]', $sql);
    }

    public function testOrWhereNotFloatArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotFloatArrayContains('coordinates', [1.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::real[]', $sql);
    }

    public function testOrWhereFloatArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereFloatArrayContainedBy('coordinates', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotFloatArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotFloatArrayContainedBy('coordinates', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotFloatArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotFloatArrayContainedBy('coordinates', [1.5, 2.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereFloatArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereFloatArrayOverlaps('coordinates', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotFloatArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotFloatArrayOverlaps('coordinates', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotFloatArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotFloatArrayOverlaps('coordinates', [1.5, 3.5]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // UUID variants
    public function testWhereNotUuidArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->whereNotUuidArrayContains('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::uuid[]', $sql);
    }

    public function testOrWhereNotUuidArrayContains()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotUuidArrayContains('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('ARRAY[?]::uuid[]', $sql);
    }

    public function testOrWhereUuidArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereUuidArrayContainedBy('related_ids', [
                'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'b1ffcd00-0d1c-5fg9-cc7e-7cc0ce491b22',
            ]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testWhereNotUuidArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->whereNotUuidArrayContainedBy('related_ids', [
                'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'b1ffcd00-0d1c-5fg9-cc7e-7cc0ce491b22',
            ]);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereNotUuidArrayContainedBy()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotUuidArrayContainedBy('related_ids', [
                'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'b1ffcd00-0d1c-5fg9-cc7e-7cc0ce491b22',
            ]);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('<@', $sql);
    }

    public function testOrWhereUuidArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereUuidArrayOverlaps('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testWhereNotUuidArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->whereNotUuidArrayOverlaps('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    public function testOrWhereNotUuidArrayOverlaps()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereNotUuidArrayOverlaps('related_ids', ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']);

        $sql = $query->toSql();

        $this->assertStringContainsString('or not', strtolower($sql));
        $this->assertStringContainsString('&&', $sql);
    }

    // ==================== Array Length Tests ====================

    public function testWhereArrayLength()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayLength('scores', '>', 2);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString(', 1), 0) > ?', $sql);
        $this->assertEquals([2], $bindings);
    }

    public function testWhereArrayLengthEquals()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayLength('tags', '=', 3);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString(', 1), 0) = ?', $sql);
        $this->assertEquals([3], $bindings);
    }

    public function testWhereArrayLengthLessThan()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayLength('scores', '<', 5);

        $sql = $query->toSql();

        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString(', 1), 0) < ?', $sql);
    }

    public function testWhereArrayLengthNotEquals()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayLength('scores', '!=', 0);

        $sql = $query->toSql();

        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString(', 1), 0) != ?', $sql);
    }

    public function testOrWhereArrayLength()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereArrayLength('scores', '>=', 2);

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString(', 1), 0) >= ?', $sql);
    }

    public function testWhereArrayLengthWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereArrayLength('scores', '>', 2)
                ->get()
        );

        $results->each(function ($item) {
            $scores = $this->parsePgArray($item->scores);
            $this->assertGreaterThan(2, count($scores));
        });
    }

    public function testWhereArrayLengthEqualsWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereArrayLength('scores', '=', 2)
                ->get()
        );

        $results->each(function ($item) {
            $scores = $this->parsePgArray($item->scores);
            $this->assertEquals(2, count($scores));
        });
    }

    // ==================== Array Empty Check Tests ====================

    public function testWhereArrayIsEmpty()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayIsEmpty('tags');

        $sql = $query->toSql();

        $this->assertStringContainsString('cardinality(', $sql);
        $this->assertStringContainsString(') = 0', $sql);
        $this->assertStringContainsString('IS NULL', $sql);
    }

    public function testOrWhereArrayIsEmpty()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereArrayIsEmpty('tags');

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('cardinality(', $sql);
    }

    public function testWhereArrayIsNotEmpty()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayIsNotEmpty('tags');

        $sql = $query->toSql();

        $this->assertStringContainsString('cardinality(', $sql);
        $this->assertStringContainsString(') > 0', $sql);
    }

    public function testOrWhereArrayIsNotEmpty()
    {
        $query = $this->getTestQuery(false)
            ->where('id', 1)
            ->orWhereArrayIsNotEmpty('tags');

        $sql = $query->toSql();

        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertStringContainsString('cardinality(', $sql);
        $this->assertStringContainsString(') > 0', $sql);
    }

    public function testWhereArrayIsNotEmptyWithRealDatabase()
    {
        $results = Collection::make(
            $this->getTestQuery()
                ->whereArrayIsNotEmpty('tags')
                ->get()
        );

        $results->each(function ($item) {
            $tags = $this->parsePgArray($item->tags);
            $this->assertNotEmpty($tags);
        });
    }

    // ==================== Combined Query Tests ====================

    public function testCombinedArrayLengthAndIsEmpty()
    {
        $query = $this->getTestQuery(false)
            ->whereArrayLength('scores', '>', 1)
            ->whereArrayIsNotEmpty('tags');

        $sql = $query->toSql();

        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString('cardinality(', $sql);
    }

    public function testCombinedArrayMethodsWithOtherConditions()
    {
        $query = $this->getTestQuery(false)
            ->whereTextArrayContains('tags', ['php'])
            ->whereArrayLength('scores', '>=', 2)
            ->where('id', '>', 1);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('@>', $sql);
        $this->assertStringContainsString('COALESCE(array_length(', $sql);
        $this->assertStringContainsString('"id" > ?', $sql);
        $this->assertEquals(['php', 2, 1], $bindings);
    }
}
