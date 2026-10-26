<?php

namespace HughCube\Laravel\Knight\Tests\Queue;

use HughCube\Laravel\Knight\Queue\FlowJobDescribe;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Schema;

class FlowJobDescribeTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue.default', 'sync');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jobs');
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function testDatabaseConnectionDetected()
    {
        $describe = new FlowJobDescribe('database', 'default', 1);

        $this->assertInstanceOf(DatabaseQueue::class, $describe->getDatabaseConnection());
        $this->assertTrue($describe->isDatabaseConnection());
    }

    public function testNonDatabaseConnectionReturnsNull()
    {
        $describe = new FlowJobDescribe('sync', 'default', 1);

        $this->assertNull($describe->getDatabaseConnection());
        $this->assertFalse($describe->isDatabaseConnection());
    }
}
