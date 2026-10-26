<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Contracts\Queue\FromFlowJob;
use HughCube\Laravel\Knight\Queue\FlowJobDescribe;
use HughCube\Laravel\Knight\Queue\Jobs\QueueFlowJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Queue\QueueManager;

class QueueFlowJobStub extends QueueFlowJob
{
    public array $idsQueue = [];
    public array $logs = [];

    protected function nextAvailableJob(): ?string
    {
        $value = array_shift($this->idsQueue);

        return $value ?: null;
    }

    public function log($level, string $message, array $context = []): void
    {
        $this->logs[] = [$level, $message];
    }
}

class QueueFlowJobFakeManager extends QueueManager
{
    protected $connections = [];

    public function __construct(array $connections, $app)
    {
        parent::__construct($app);
        $this->connections = $connections;
    }

    public function connection($name = null)
    {
        return $this->connections[$name];
    }
}

class QueueFlowJobFakeConnection
{
    public string $name;
    public ?QueueFlowJobFakeQueueJob $nextJob;
    public ?object $pushedJob = null;
    public ?string $pushedQueue = null;
    public ?string $lastQueueRequest = null;
    private string $pushResult;

    public function __construct(string $name, ?QueueFlowJobFakeQueueJob $nextJob, string $pushResult = 'pushed-id')
    {
        $this->name = $name;
        $this->nextJob = $nextJob;
        $this->pushResult = $pushResult;
    }

    public function getQueue($queue)
    {
        $this->lastQueueRequest = $queue;

        return $queue;
    }

    public function pop($queue)
    {
        return $this->nextJob;
    }

    public function getConnectionName(): string
    {
        return $this->name;
    }

    public function push($job, $data = '', $queue = null)
    {
        $this->pushedJob = $job;
        $this->pushedQueue = $queue;

        return $this->pushResult;
    }
}

class QueueFlowJobFakeQueueJob
{
    public bool $deleted = false;
    private string $rawBody;
    private string $jobId;

    public function __construct(string $rawBody, string $jobId)
    {
        $this->rawBody = $rawBody;
        $this->jobId = $jobId;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }
}

class QueueFlowJobFlowJobSpy implements FromFlowJob
{
    public ?FlowJobDescribe $describe = null;
    private bool $delayDelete;

    public function __construct(bool $delayDelete)
    {
        $this->delayDelete = $delayDelete;
    }

    public function setFlowJobDescribe(FlowJobDescribe $describe)
    {
        $this->describe = $describe;
    }

    public function isDelayDeleteFlowJob(): bool
    {
        return $this->delayDelete;
    }
}

class QueueFlowJobTest extends TestCase
{
    public function testActionStopsWhenNoJobs()
    {
        $job = new QueueFlowJobStub([
            'from_connection' => 'sync',
            'to_connection' => 'sync',
            'max_time' => 1,
        ]);
        $job->idsQueue = ['job1', 'job2', null];

        $this->callMethod($job, 'loadParameters');
        $this->callMethod($job, 'action');

        $this->assertNotEmpty($job->logs);
        $this->assertStringContainsString('推送任务2个', $job->logs[0][1]);
    }

    public function testGetMaxTimeUsesDefaultAndProvidedValue()
    {
        $job = new QueueFlowJobStub([
            'from_connection' => 'sync',
            'to_connection' => 'sync',
        ]);
        $this->callMethod($job, 'loadParameters');
        $this->assertSame(60, $this->callMethod($job, 'getMaxTime'));

        $job = new QueueFlowJobStub([
            'from_connection' => 'sync',
            'to_connection' => 'sync',
            'max_time' => 5,
        ]);
        $this->callMethod($job, 'loadParameters');
        $this->assertSame(5, $this->callMethod($job, 'getMaxTime'));
    }

    public function testParseJobReturnsUnserializedObject()
    {
        $job = new QueueFlowJobStub([
            'from_connection' => 'sync',
            'to_connection' => 'sync',
        ]);

        $payload = json_encode([
            'data' => [
                'command' => serialize(new \stdClass()),
            ],
        ]);

        $parsed = $this->callMethod($job, 'parseJob', [$payload]);

        $this->assertInstanceOf(\stdClass::class, $parsed);
    }

    public function testNextAvailableJobReturnsNullWhenEmpty()
    {
        $fromConnection = new QueueFlowJobFakeConnection('from', null);
        $toConnection = new QueueFlowJobFakeConnection('to', null);
        $this->app->instance(QueueManager::class, new QueueFlowJobFakeManager([
            'from' => $fromConnection,
            'to' => $toConnection,
        ], $this->app));

        $job = new QueueFlowJob([
            'from_connection' => 'from',
            'to_connection' => 'to',
        ]);
        $this->callMethod($job, 'loadParameters');

        $result = $this->callMethod($job, 'nextAvailableJob');

        $this->assertNull($result);
        $this->assertNull($toConnection->pushedJob);
    }

    public function testNextAvailableJobDeletesNonFlowJob()
    {
        $payload = json_encode([
            'data' => [
                'command' => serialize((object) ['name' => 'plain']),
            ],
        ]);
        $fromJob = new QueueFlowJobFakeQueueJob($payload, 'job-1');
        $fromConnection = new QueueFlowJobFakeConnection('from', $fromJob);
        $toConnection = new QueueFlowJobFakeConnection('to', null, 'pushed-1');

        $this->app->instance(QueueManager::class, new QueueFlowJobFakeManager([
            'from' => $fromConnection,
            'to' => $toConnection,
        ], $this->app));

        $job = new QueueFlowJob([
            'from_connection' => 'from',
            'to_connection' => 'to',
            'from_queue' => 'from-queue',
            'to_queue' => 'to-queue',
        ]);
        $this->callMethod($job, 'loadParameters');

        $result = $this->callMethod($job, 'nextAvailableJob');

        $this->assertSame('pushed-1', $result);
        $this->assertTrue($fromJob->deleted);
        $this->assertSame('from-queue', $fromConnection->lastQueueRequest);
        $this->assertSame('to-queue', $toConnection->pushedQueue);
        $this->assertInstanceOf(\stdClass::class, $toConnection->pushedJob);
        $this->assertSame('plain', $toConnection->pushedJob->name);
    }

    public function testNextAvailableJobSkipsDeleteWhenFlowJobDelays()
    {
        $flowJob = new QueueFlowJobFlowJobSpy(true);
        $payload = json_encode([
            'data' => [
                'command' => serialize($flowJob),
            ],
        ]);
        $fromJob = new QueueFlowJobFakeQueueJob($payload, 'job-2');
        $fromConnection = new QueueFlowJobFakeConnection('from', $fromJob);
        $toConnection = new QueueFlowJobFakeConnection('to', null, 'pushed-2');

        $this->app->instance(QueueManager::class, new QueueFlowJobFakeManager([
            'from' => $fromConnection,
            'to' => $toConnection,
        ], $this->app));

        $job = new QueueFlowJob([
            'from_connection' => 'from',
            'to_connection' => 'to',
        ]);
        $this->callMethod($job, 'loadParameters');

        $result = $this->callMethod($job, 'nextAvailableJob');

        $this->assertSame('pushed-2', $result);
        $this->assertFalse($fromJob->deleted);
        $this->assertInstanceOf(QueueFlowJobFlowJobSpy::class, $toConnection->pushedJob);
        $this->assertInstanceOf(FlowJobDescribe::class, $toConnection->pushedJob->describe);
    }
}
