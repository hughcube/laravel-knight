<?php

namespace HughCube\Laravel\Knight\Tests\ServiceProvider;

use Closure;
use HughCube\Laravel\Knight\Database\Query\Grammars\PostgresGrammar as KnightPostgresGrammar;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;
use ReflectionProperty;
use RuntimeException;

class PostgresJsonOverlapsGrammarTest extends TestCase
{
    private array $originalResolvers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalResolvers = $this->getResolvers();
    }

    protected function tearDown(): void
    {
        $this->setResolvers($this->originalResolvers);

        parent::tearDown();
    }

    private function makeMySqlConnection(): MySqlConnection
    {
        $this->assertTrue(true);

        return new MySqlConnection($this->makePdoResolver(), '', '', ['driver' => 'mysql']);
    }

    private function makePostgresConnection(): PostgresConnection
    {
        return new PostgresConnection($this->makePdoResolver(), '', '', ['driver' => 'pgsql']);
    }

    private function makePdoResolver(): Closure
    {
        return function () {
            throw new RuntimeException('PDO connection is not available for SQL-only tests.');
        };
    }

    private function getResolvers(): array
    {
        $property = new ReflectionProperty(Connection::class, 'resolvers');
        $property->setAccessible(true);

        $resolvers = $property->getValue();

        return is_array($resolvers) ? $resolvers : [];
    }

    private function setResolvers(array $resolvers): void
    {
        $property = new ReflectionProperty(Connection::class, 'resolvers');
        $property->setAccessible(true);
        $property->setValue(null, $resolvers);
    }

    private function setPgsqlResolver(?Closure $resolver): void
    {
        $resolvers = $this->getResolvers();

        if ($resolver === null) {
            unset($resolvers['pgsql']);
        } else {
            $resolvers['pgsql'] = $resolver;
        }

        $this->setResolvers($resolvers);
    }

    private function makeDbManager(array $connections): object
    {
        return new class($connections)
        {
            private array $connections;

            public function __construct(array $connections)
            {
                $this->connections = $connections;
            }

            public function getConnections(): array
            {
                return $this->connections;
            }
        };
    }

    public function testRegisterConnectionResolverCreatesConnectionWhenMissingResolver(): void
    {
        $this->setPgsqlResolver(null);

        KnightPostgresGrammar::registerConnectionResolver();

        $resolvers = $this->getResolvers();
        $this->assertArrayHasKey('pgsql', $resolvers);

        $resolver = $resolvers['pgsql'];
        $connection = $resolver($this->makePdoResolver(), '', '', ['driver' => 'pgsql']);

        $this->assertInstanceOf(PostgresConnection::class, $connection);
        $this->assertInstanceOf(KnightPostgresGrammar::class, $connection->getQueryGrammar());
    }

    public function testRegisterConnectionResolverWrapsExistingResolver(): void
    {
        $called = false;

        $existingResolver = function ($connection, $database, $prefix, $config) use (&$called) {
            $called = true;

            return new PostgresConnection($connection, $database, $prefix, $config);
        };

        $this->setPgsqlResolver($existingResolver);

        KnightPostgresGrammar::registerConnectionResolver();

        $resolvers = $this->getResolvers();
        $resolver = $resolvers['pgsql'];
        $connection = $resolver($this->makePdoResolver(), '', '', ['driver' => 'pgsql']);

        $this->assertTrue($called);
        $this->assertInstanceOf(PostgresConnection::class, $connection);
        $this->assertInstanceOf(KnightPostgresGrammar::class, $connection->getQueryGrammar());
    }

    public function testApplyToExistingConnectionsUpdatesPgsqlOnly(): void
    {
        $pgsqlConnection = $this->makePostgresConnection();
        $pgsqlConnection->setQueryGrammar(new BasePostgresGrammar($pgsqlConnection));

        $mysqlConnection = $this->makeMySqlConnection();
        $mysqlConnection->setQueryGrammar(new MySqlGrammar($mysqlConnection));

        $dbManager = $this->makeDbManager([$pgsqlConnection, $mysqlConnection, new \stdClass()]);

        $this->app->instance('db', $dbManager);
        $this->app->make('db');

        KnightPostgresGrammar::applyToExistingConnections();

        $this->assertInstanceOf(KnightPostgresGrammar::class, $pgsqlConnection->getQueryGrammar());
        $this->assertInstanceOf(MySqlGrammar::class, $mysqlConnection->getQueryGrammar());
    }
}
