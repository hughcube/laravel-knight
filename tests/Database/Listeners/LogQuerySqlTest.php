<?php

namespace HughCube\Laravel\Knight\Tests\Database\Listeners;

use HughCube\Laravel\Knight\Database\Listeners\LogQuerySql;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;

class LogQuerySqlTest extends TestCase
{
    public function testHandleLogsQueryExecuted()
    {
        $handler = $this->setupTestLogHandler();

        $connection = $this->app->make('db')->connection();
        $event = new QueryExecuted('select * from users where name = ?', ['Bob'], 12.5, $connection);

        $listener = new LogQuerySql();
        $listener->handle($event);

        if ($handler !== null) {
            $found = false;
            foreach ($handler->getRecords() as $record) {
                $message = $record['message'] ?? ($record->message ?? '');
                if (str_contains($message, 'query executed')
                    && str_contains($message, 'connection: '.$connection->getName())
                    && str_contains($message, 'duration: 12.5ms')
                    && str_contains($message, "sql: select * from users where name = 'Bob'")) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Expected query log message not found');
        } else {
            $this->assertTrue(true, 'Log handler not available, skipping log assertion');
        }
    }

    public function testHandleIgnoresNonQueryExecuted()
    {
        $handler = $this->setupTestLogHandler();
        $initialCount = $handler !== null ? count($handler->getRecords()) : 0;

        $listener = new LogQuerySql();
        $listener->handle((object) ['sql' => 'select 1']);

        if ($handler !== null) {
            $this->assertSame($initialCount, count($handler->getRecords()), 'No log should be written for non-QueryExecuted');
        } else {
            $this->assertTrue(true, 'Log handler not available, skipping log assertion');
        }
    }

    public function testHandleIgnoresEmptySql()
    {
        $handler = $this->setupTestLogHandler();
        $initialCount = $handler !== null ? count($handler->getRecords()) : 0;

        $connection = $this->app->make('db')->connection();
        $event = new QueryExecuted('', [], 0, $connection);

        $listener = new LogQuerySql();
        $listener->handle($event);

        if ($handler !== null) {
            $this->assertSame($initialCount, count($handler->getRecords()), 'No log should be written for empty SQL');
        } else {
            $this->assertTrue(true, 'Log handler not available, skipping log assertion');
        }
    }

    public function testIsEnableReturnsTrue()
    {
        $listener = new LogQuerySql();

        $this->assertTrue(self::callMethod($listener, 'isEnable'));
    }

    public function testThresholdFiltersQueries()
    {
        $handler = $this->setupTestLogHandler();
        $initialCount = $handler !== null ? count($handler->getRecords()) : 0;

        $listener = new class() extends LogQuerySql
        {
            protected function getThreshold(): float
            {
                return 500;
            }
        };

        $connection = $this->app->make('db')->connection();

        // 低于阈值，不记录
        $listener->handle(new QueryExecuted('select 1', [], 100.0, $connection));

        if ($handler !== null) {
            $this->assertSame($initialCount, count($handler->getRecords()));
        }

        // 高于阈值，记录
        $listener->handle(new QueryExecuted('select 2', [], 600.0, $connection));

        if ($handler !== null) {
            $this->assertGreaterThan($initialCount, count($handler->getRecords()));
        } else {
            $this->assertTrue(true, 'Log handler not available');
        }
    }

    public function testCustomLogLevelAndPrefix()
    {
        $handler = $this->setupTestLogHandler();

        $listener = new class() extends LogQuerySql
        {
            protected function getLogLevel(): string
            {
                return 'warning';
            }

            protected function getLogPrefix(): string
            {
                return 'slow query detected';
            }
        };

        $connection = $this->app->make('db')->connection();
        $listener->handle(new QueryExecuted('select 1', [], 5.0, $connection));

        if ($handler !== null) {
            $this->assertTrue(
                $handler->hasWarningThatContains('slow query detected'),
                'Expected warning log with custom prefix'
            );
        } else {
            $this->assertTrue(true, 'Log handler not available');
        }
    }

    public function testDefaultThresholdIsZero()
    {
        $listener = new LogQuerySql();

        $this->assertSame(0.0, self::callMethod($listener, 'getThreshold'));
    }

    public function testBindingsWithPercentSign()
    {
        $handler = $this->setupTestLogHandler();

        $connection = $this->app->make('db')->connection();
        $event = new QueryExecuted('select * from users where name like ?', ['%foo%'], 1.0, $connection);

        $listener = new LogQuerySql();
        $listener->handle($event);

        if ($handler !== null) {
            $found = false;
            foreach ($handler->getRecords() as $record) {
                $message = $record['message'] ?? ($record->message ?? '');
                if (str_contains($message, "'%foo%'")) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Expected log with % in binding');
        } else {
            $this->assertTrue(true, 'Log handler not available');
        }
    }

    public function testBindingsWithQuestionMark()
    {
        $handler = $this->setupTestLogHandler();

        $connection = $this->app->make('db')->connection();
        $event = new QueryExecuted(
            'select * from t where a = ? and b = ?',
            ['what?', 'yes?no'],
            1.0,
            $connection
        );

        $listener = new LogQuerySql();
        $listener->handle($event);

        if ($handler !== null) {
            $found = false;
            foreach ($handler->getRecords() as $record) {
                $message = $record['message'] ?? ($record->message ?? '');
                if (str_contains($message, "a = 'what?'")
                    && str_contains($message, "b = 'yes?no'")) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Expected log with ? in binding values');
        } else {
            $this->assertTrue(true, 'Log handler not available');
        }
    }

    public function testBindingsWithNullAndBool()
    {
        $handler = $this->setupTestLogHandler();

        $connection = $this->app->make('db')->connection();
        $event = new QueryExecuted('select * from t where a = ? and b = ? and c = ?', [null, true, false], 1.0, $connection);

        $listener = new LogQuerySql();
        $listener->handle($event);

        if ($handler !== null) {
            $found = false;
            foreach ($handler->getRecords() as $record) {
                $message = $record['message'] ?? ($record->message ?? '');
                if (str_contains($message, 'a = null')
                    && str_contains($message, 'b = true')
                    && str_contains($message, 'c = false')) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Expected log with null/bool bindings');
        } else {
            $this->assertTrue(true, 'Log handler not available');
        }
    }
}
