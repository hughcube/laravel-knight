<?php

namespace HughCube\Laravel\Knight\Tests\Database\Migrations;

use HughCube\Laravel\Knight\Database\Migrations\Migration;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as PostgresSchemaGrammar;
use ReflectionProperty;

class MigrationTest extends TestCase
{
    public function testConstructorRegistersMixins(): void
    {
        $this->setMigrationMixinRegistered(false);

        new class() extends Migration {
        };

        $this->assertTrue($this->isMigrationMixinRegistered());
        $this->assertTrue(method_exists(Blueprint::class, 'hasMacro'));
        $this->assertTrue(Blueprint::hasMacro('knightColumns'));
        $this->assertTrue(Blueprint::hasMacro('knightColumnsReversed'));
        $this->assertTrue(method_exists(PostgresSchemaGrammar::class, 'hasMacro'));
        $this->assertTrue(PostgresSchemaGrammar::hasMacro('compileKnightCreateSequence'));
    }

    public function testGetDbReturnsConfiguredConnection(): void
    {
        $migration = new class() extends Migration
        {
            protected $connection = 'sqlite';
        };

        /** @var Connection $connection */
        $connection = self::callMethod($migration, 'getDB');

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertSame('sqlite', $connection->getDriverName());
    }

    private function setMigrationMixinRegistered(bool $value): void
    {
        $property = new ReflectionProperty(Migration::class, 'mixinRegistered');
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    private function isMigrationMixinRegistered(): bool
    {
        $property = new ReflectionProperty(Migration::class, 'mixinRegistered');
        $property->setAccessible(true);

        return (bool) $property->getValue();
    }
}
