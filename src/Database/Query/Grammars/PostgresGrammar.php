<?php

namespace HughCube\Laravel\Knight\Database\Query\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

/**
 * PostgreSQL Grammar 扩展，提供 JSON Overlaps 支持.
 *
 * PostgreSQL 原生不支持 MySQL 的 JSON_OVERLAPS 函数，
 * 此类通过重写 compileJsonOverlaps 方法提供兼容实现。
 *
 * 使用方式:
 *   // 在 AppServiceProvider::boot() 或需要的地方调用
 *   PostgresGrammar::registerConnectionResolver();
 *   PostgresGrammar::applyToExistingConnections();
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html
 */
class PostgresGrammar extends BasePostgresGrammar
{
    /**
     * Compile a "JSON overlaps" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     * @return string
     */
    protected function compileJsonOverlaps($column, $value)
    {
        $column = str_replace('->>', '->', $this->wrap($column));
        $lhs = '('.$column.')::jsonb';
        $rhs = $value.'::jsonb';
        $alias = 'json_overlaps_values';

        return 'exists (select 1 from (select '.$rhs.' as v, '.$lhs.' as lhs) as '.$alias.' where case'
            ." when jsonb_typeof({$alias}.lhs) = 'object' and jsonb_typeof({$alias}.v) = 'object' then "
            .'exists (select 1 from jsonb_each('.$alias.'.lhs) l join jsonb_each('.$alias.'.v) r on l.key = r.key and l.value = r.value)'
            ." when jsonb_typeof({$alias}.lhs) = 'array' and jsonb_typeof({$alias}.v) = 'array' then "
            .'exists (select 1 from jsonb_array_elements('.$alias.'.lhs) l join jsonb_array_elements('.$alias.'.v) r on l = r)'
            ." when jsonb_typeof({$alias}.lhs) = 'array' then "
            .'exists (select 1 from jsonb_array_elements('.$alias.'.lhs) l where l = '.$alias.'.v)'
            ." when jsonb_typeof({$alias}.v) = 'array' then "
            .'exists (select 1 from jsonb_array_elements('.$alias.'.v) r where r = '.$alias.'.lhs)'
            .' else '.$alias.'.lhs = '.$alias.'.v end)';
    }

    /**
     * 注册连接解析器，使新的 PostgreSQL 连接使用此 Grammar.
     */
    public static function registerConnectionResolver(): void
    {
        if (! class_exists(PostgresConnection::class)) {
            return;
        }

        if (! method_exists(Connection::class, 'resolverFor')) {
            return;
        }

        $existingResolver = method_exists(Connection::class, 'getResolver')
            ? Connection::getResolver('pgsql')
            : null;

        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) use ($existingResolver) {
            $resolvedConnection = $existingResolver
                ? $existingResolver($connection, $database, $prefix, $config)
                : new PostgresConnection($connection, $database, $prefix, $config);

            if (method_exists($resolvedConnection, 'setQueryGrammar')) {
                $resolvedConnection->setQueryGrammar(static::createInstance($resolvedConnection));
            }

            return $resolvedConnection;
        });
    }

    /**
     * 为已存在的 PostgreSQL 连接应用此 Grammar.
     */
    public static function applyToExistingConnections(): void
    {
        if (! function_exists('app') || ! app()->resolved('db')) {
            return;
        }

        $db = app('db');
        if (! method_exists($db, 'getConnections')) {
            return;
        }

        foreach ($db->getConnections() as $connection) {
            if (method_exists($connection, 'getDriverName')
                && $connection->getDriverName() === 'pgsql'
                && method_exists($connection, 'setQueryGrammar')
            ) {
                $connection->setQueryGrammar(static::createInstance($connection));
            }
        }
    }

    /**
     * 创建 Grammar 实例, 兼容 Laravel 新版本和旧版本的构造函数签名.
     *
     * @param  Connection  $connection
     * @return static
     */
    protected static function createInstance(Connection $connection)
    {
        $ref = new \ReflectionClass(parent::class);
        $constructor = $ref->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return new static($connection);
        }

        return new static();
    }
}
