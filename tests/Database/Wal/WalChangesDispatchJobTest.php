<?php

namespace HughCube\Laravel\Knight\Tests\Database\Wal;

use HughCube\Laravel\Knight\Contracts\Database\HasWalHandler;
use HughCube\Laravel\Knight\Database\Eloquent\Model;
use HughCube\Laravel\Knight\Database\Eloquent\Traits\HasWalHandlerTrait;
use HughCube\Laravel\Knight\Database\Wal\WalChangeRecord;
use HughCube\Laravel\Knight\Jobs\WalChangesDispatchJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class WalChangesDispatchJobTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        Schema::dropIfExists('wal_dispatch_job_test_items');
        Schema::create('wal_dispatch_job_test_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->default('');
            $table->timestamps();
        });
    }

    public function testConstructorStoresRecord()
    {
        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1, 'name' => 'test'], [], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);

        $this->assertSame($record, $job->getRecord());
        $this->assertSame(1, $job->getRecord()->getId());
    }

    public function testHandleDispatchesStringEvent()
    {
        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1, 'name' => 'test'], [], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);

        $receivedEvents = [];
        Event::listen('wal:*', function ($eventName, $payload) use (&$receivedEvents) {
            $receivedEvents[] = ['name' => $eventName, 'record' => $payload[0]];
        });

        $job->handle(app('events'));

        $this->assertCount(1, $receivedEvents);
        $this->assertSame('wal:wal_dispatch_job_test_items:insert', $receivedEvents[0]['name']);
        $this->assertSame(1, $receivedEvents[0]['record']->getId());
    }

    public function testHandleDispatchesUpdateEvent()
    {
        $record = new WalChangeRecord('update', 'wal_dispatch_job_test_items', null, 5, 'id', ['id' => 5, 'name' => 'updated'], [], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);

        $receivedEvents = [];
        Event::listen('wal:wal_dispatch_job_test_items:update', function (WalChangeRecord $record) use (&$receivedEvents) {
            $receivedEvents[] = $record;
        });

        $job->handle(app('events'));

        $this->assertCount(1, $receivedEvents);
        $this->assertSame(5, $receivedEvents[0]->getId());
        $this->assertTrue($receivedEvents[0]->isUpdate());
    }

    public function testHandleDispatchesDeleteEvent()
    {
        $record = new WalChangeRecord('delete', 'wal_dispatch_job_test_items', null, 99, 'id', [], ['id' => 99], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);

        $receivedEvents = [];
        Event::listen('wal:wal_dispatch_job_test_items:delete', function (WalChangeRecord $record) use (&$receivedEvents) {
            $receivedEvents[] = $record;
        });

        $job->handle(app('events'));

        $this->assertCount(1, $receivedEvents);
        $this->assertSame(99, $receivedEvents[0]->getId());
        $this->assertTrue($receivedEvents[0]->isDelete());
    }

    public function testHandleDoesNotCallOnKnightModelChanged()
    {
        WalDispatchJobTestModel::$modelChangedCount = 0;

        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1, 'name' => 'test'], [], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);
        $job->handle(app('events'));

        $this->assertSame(0, WalDispatchJobTestModel::$modelChangedCount);
    }

    public function testJobIsSerializable()
    {
        $record = new WalChangeRecord('update', 'wal_dispatch_job_test_items', 'wal_dispatch_job_test_items_p1', 7, 'id', ['id' => 7, 'name' => 'serialized'], ['id' => 7, 'name' => 'old'], WalDispatchJobTestModel::class);
        $job = new WalChangesDispatchJob($record);

        /** @var WalChangesDispatchJob $unserialized */
        $unserialized = unserialize(serialize($job));
        $r = $unserialized->getRecord();

        $this->assertSame(7, $r->getId());
        $this->assertTrue($r->isUpdate());
        $this->assertSame(['id' => 7, 'name' => 'serialized'], $r->getColumns());
        $this->assertSame(['id' => 7, 'name' => 'old'], $r->getOldColumns());
        $this->assertSame('wal_dispatch_job_test_items_p1', $r->getPartitionTable());
        $this->assertTrue($r->isFromPartition());
        $this->assertSame(WalDispatchJobTestModel::class, $r->getModelClass());
    }

    public function testJobCanBeDispatchedToQueue()
    {
        Queue::fake();

        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1], [], WalDispatchJobTestModel::class);
        WalChangesDispatchJob::dispatch($record);

        Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
            return $job->getRecord()->getId() === 1;
        });
    }

    public function testJobCanBeDispatchedToSpecificQueue()
    {
        Queue::fake();

        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1], [], WalDispatchJobTestModel::class);
        WalChangesDispatchJob::dispatch($record)->onQueue('wal-events');

        Queue::assertPushedOn('wal-events', WalChangesDispatchJob::class);
    }

    public function testJobCanBeDispatchedToSyncConnection()
    {
        Queue::fake();

        $record = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1], [], WalDispatchJobTestModel::class);
        WalChangesDispatchJob::dispatch($record)->onConnection('sync');

        Queue::assertPushed(WalChangesDispatchJob::class, function (WalChangesDispatchJob $job) {
            return $job->connection === 'sync';
        });
    }

    public function testWildcardListenerReceivesAllEvents()
    {
        $receivedEvents = [];
        Event::listen('wal:*', function ($eventName, $payload) use (&$receivedEvents) {
            $receivedEvents[] = $eventName;
        });

        $insert = new WalChangeRecord('insert', 'wal_dispatch_job_test_items', null, 1, 'id', ['id' => 1], [], WalDispatchJobTestModel::class);
        (new WalChangesDispatchJob($insert))->handle(app('events'));

        $delete = new WalChangeRecord('delete', 'wal_dispatch_job_test_items', null, 2, 'id', [], ['id' => 2], WalDispatchJobTestModel::class);
        (new WalChangesDispatchJob($delete))->handle(app('events'));

        $this->assertCount(2, $receivedEvents);
        $this->assertSame('wal:wal_dispatch_job_test_items:insert', $receivedEvents[0]);
        $this->assertSame('wal:wal_dispatch_job_test_items:delete', $receivedEvents[1]);
    }
}

/**
 * @property int $id
 * @property string $name
 */
class WalDispatchJobTestModel extends Model implements HasWalHandler
{
    use HasWalHandlerTrait;

    protected $table = 'wal_dispatch_job_test_items';

    protected $fillable = ['id', 'name'];

    /** @var int */
    public static $modelChangedCount = 0;

    public function onKnightModelChanged(): void
    {
        static::$modelChangedCount++;
    }
}
