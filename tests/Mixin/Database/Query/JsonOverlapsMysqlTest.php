<?php

namespace HughCube\Laravel\Knight\Tests\Mixin\Database\Query;

use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class JsonOverlapsMysqlTest extends TestCase
{
    private static ?bool $mysqlJsonOverlapsPathSupported = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfMysqlNotConfigured();

        $this->setUpJsonTable();
    }

    public function testWhereJsonOverlapsWithArray(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->whereJsonOverlaps('tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1], $this->castIds($ids));
    }

    public function testWhereJsonOverlapsWithObject(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->whereJsonOverlaps('meta', ['role' => 'admin'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1, 3], $this->castIds($ids));
    }

    public function testWhereJsonOverlapsWithScalar(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->whereJsonOverlaps('scalar', 'php')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1], $this->castIds($ids));
    }

    public function testWhereJsonOverlapsWithPath(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();
        $this->skipIfMysqlJsonOverlapsPathNotSupported();

        $ids = $this->getQuery()
            ->whereJsonOverlaps('payload->settings->tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1, 3], $this->castIds($ids));
    }

    public function testWhereJsonDoesntOverlap(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->whereJsonDoesntOverlap('tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([2, 3, 4], $this->castIds($ids));
    }

    public function testWhereJsonDoesntOverlapWithPath(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();
        $this->skipIfMysqlJsonOverlapsPathNotSupported();

        $ids = $this->getQuery()
            ->whereJsonDoesntOverlap('payload->settings->tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([2, 4], $this->castIds($ids));
    }

    public function testOrWhereJsonOverlaps(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->where('id', 2)
            ->orWhereJsonOverlaps('tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([1, 2], $this->castIds($ids));
    }

    public function testOrWhereJsonDoesntOverlap(): void
    {
        $this->skipIfMysqlJsonOverlapsUnavailable();

        $ids = $this->getQuery()
            ->where('id', 2)
            ->orWhereJsonDoesntOverlap('tags', ['php'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([2, 3, 4], $this->castIds($ids));
    }

    private function getQuery()
    {
        $query = DB::connection('mysql')->table('knight_json_overlaps_test');

        if (method_exists($query, 'useWritePdo')) {
            $query->useWritePdo();
        }

        return $query;
    }

    private function setUpJsonTable(): void
    {
        $connection = DB::connection('mysql');

        try {
            $connection->statement('DROP TABLE IF EXISTS knight_json_overlaps_test');
            $connection->statement(
                'CREATE TABLE knight_json_overlaps_test (
                    id INTEGER PRIMARY KEY,
                    tags JSON NULL,
                    meta JSON NULL,
                    payload JSON NULL,
                    scalar JSON NULL
                )'
            );

            $connection->table('knight_json_overlaps_test')->insert($this->seedRows());
        } catch (\Throwable $exception) {
            $this->markTestSkipped('MySQL JSON overlaps setup failed: '.$exception->getMessage());
        }
    }

    private function seedRows(): array
    {
        return [
            [
                'id' => 1,
                'tags' => $this->encodeJson(['php', 'laravel']),
                'meta' => $this->encodeJson(['role' => 'admin', 'team' => 'alpha']),
                'payload' => $this->encodeJson(['settings' => ['tags' => ['php', 'mysql']], 'status' => 'active']),
                'scalar' => $this->encodeJson('php'),
            ],
            [
                'id' => 2,
                'tags' => $this->encodeJson(['python', 'django']),
                'meta' => $this->encodeJson(['role' => 'user', 'team' => 'beta']),
                'payload' => $this->encodeJson(['settings' => ['tags' => ['python']], 'status' => 'inactive']),
                'scalar' => $this->encodeJson('python'),
            ],
            [
                'id' => 3,
                'tags' => $this->encodeJson(['go', 'rust']),
                'meta' => $this->encodeJson(['role' => 'admin', 'team' => 'beta', 'flag' => true]),
                'payload' => $this->encodeJson(['settings' => ['tags' => ['rust', 'php']], 'status' => 'active']),
                'scalar' => $this->encodeJson('go'),
            ],
            [
                'id' => 4,
                'tags' => $this->encodeJson([]),
                'meta' => $this->encodeJson(['role' => 'guest']),
                'payload' => $this->encodeJson(['settings' => ['tags' => []], 'status' => 'disabled']),
                'scalar' => $this->encodeJson(null),
            ],
        ];
    }

    private function encodeJson($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function castIds(array $ids): array
    {
        return array_map('intval', $ids);
    }

    private function skipIfBuilderJsonOverlapsNotSupported(): void
    {
        if (! method_exists(Builder::class, 'whereJsonOverlaps')) {
            $this->markTestSkipped('Query builder does not support whereJsonOverlaps (Laravel version too old).');
        }
    }

    private function skipIfMysqlJsonOverlapsNotSupported(): void
    {
        try {
            DB::connection('mysql')->select('select json_overlaps(?, ?) as result', ['[]', '[]']);
        } catch (\Throwable $exception) {
            $this->markTestSkipped('MySQL JSON_OVERLAPS is not supported: '.$exception->getMessage());
        }
    }

    private function skipIfMysqlJsonOverlapsUnavailable(): void
    {
        $this->skipIfBuilderJsonOverlapsNotSupported();
        $this->skipIfMysqlJsonOverlapsNotSupported();
    }

    private function skipIfMysqlJsonOverlapsPathNotSupported(): void
    {
        if ($this->isMysqlJsonOverlapsPathSupported()) {
            return;
        }

        $this->markTestSkipped('MySQL JSON_OVERLAPS does not support JSON path arguments.');
    }

    private function isMysqlJsonOverlapsPathSupported(): bool
    {
        if (self::$mysqlJsonOverlapsPathSupported !== null) {
            return self::$mysqlJsonOverlapsPathSupported;
        }

        try {
            DB::connection('mysql')->select(
                'select json_overlaps(?, ?, ?) as result',
                ['{"settings":{"tags":["php"]}}', '["php"]', '$.settings.tags']
            );
            self::$mysqlJsonOverlapsPathSupported = true;
        } catch (\Throwable $exception) {
            self::$mysqlJsonOverlapsPathSupported = false;
        }

        return self::$mysqlJsonOverlapsPathSupported;
    }
}
