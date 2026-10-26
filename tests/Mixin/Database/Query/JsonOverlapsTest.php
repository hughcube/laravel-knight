<?php

namespace HughCube\Laravel\Knight\Tests\Mixin\Database\Query;

use HughCube\Laravel\Knight\Database\Query\Grammars\PostgresGrammar as KnightPostgresGrammar;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use RuntimeException;

class JsonOverlapsTest extends TestCase
{
    private function makeMySqlConnection(): MySqlConnection
    {
        return new MySqlConnection($this->makePdoResolver(), '', '', ['driver' => 'mysql']);
    }

    private function makePostgresConnection(): PostgresConnection
    {
        return new PostgresConnection($this->makePdoResolver(), '', '', ['driver' => 'pgsql']);
    }

    private function makePdoResolver(): callable
    {
        return function () {
            throw new RuntimeException('PDO connection is not available for SQL-only tests.');
        };
    }

    public function testWhereJsonDoesntOverlapAddsNotClause(): void
    {
        $this->skipIfBuilderJsonDoesntOverlapNotSupported();

        $query = $this->makeMySqlConnection()->table('knight_json_test');
        $query->whereJsonDoesntOverlap('tags', ['php']);

        $this->assertCount(1, $query->wheres);
        $where = $query->wheres[0];

        $this->assertSame('JsonOverlaps', $where['type']);
        $this->assertTrue($where['not']);
        $this->assertSame('and', $where['boolean']);
        $this->assertSame(['["php"]'], $query->getBindings());
    }

    public function testOrWhereJsonDoesntOverlapAddsOrClause(): void
    {
        $this->skipIfBuilderOrWhereJsonDoesntOverlapNotSupported();

        $query = $this->makeMySqlConnection()->table('knight_json_test');
        $query->where('id', 1)->orWhereJsonDoesntOverlap('tags', ['php']);

        $where = $query->wheres[count($query->wheres) - 1];

        $this->assertSame('JsonOverlaps', $where['type']);
        $this->assertTrue($where['not']);
        $this->assertSame('or', $where['boolean']);
    }

    public function testKnightPostgresGrammarCompileJsonOverlapsBuildsJsonbExpression(): void
    {
        $grammar = new KnightPostgresGrammar($this->makePostgresConnection());

        $sql = self::callMethod($grammar, 'compileJsonOverlaps', ['payload->items', '?']);

        $this->assertStringContainsString('jsonb_typeof', $sql);
        $this->assertStringContainsString('jsonb_array_elements', $sql);
        $this->assertStringContainsString('jsonb_each', $sql);
        $this->assertStringContainsString('::jsonb', $sql);
    }

    private function skipIfBuilderJsonDoesntOverlapNotSupported(): void
    {
        if (! method_exists(Builder::class, 'whereJsonDoesntOverlap')
            || ! method_exists(Builder::class, 'whereJsonOverlaps')
        ) {
            $this->markTestSkipped('Query builder does not support whereJsonDoesntOverlap (Laravel version too old).');
        }
    }

    private function skipIfBuilderOrWhereJsonDoesntOverlapNotSupported(): void
    {
        if (! method_exists(Builder::class, 'orWhereJsonDoesntOverlap')) {
            $this->markTestSkipped('Query builder does not support orWhereJsonDoesntOverlap (Laravel version too old).');
        }
    }
}
