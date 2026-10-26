<?php

namespace HughCube\Laravel\Knight\Tests\OPcache\Jobs;

use HughCube\Laravel\Knight\OPcache\Jobs\WatchOpcacheScriptsJob;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\PUrl\Url;

class WatchOpcacheScriptsJobStub extends WatchOpcacheScriptsJob
{
    public array $logs = [];

    public function log($level, string $message, array $context = []): void
    {
        $this->logs[] = [$level, $message];
    }
}

class FakeOPcache extends OPcache
{
    public ?Url $url = null;
    public array $remoteScripts = [];
    public bool $remoteCalled = false;

    public function getUrl($url = null, $useAppHost = true): ?Url
    {
        return $this->url;
    }

    public function getRemoteScripts($url = null, $timeout = 5, $useAppHost = true): array
    {
        $this->remoteCalled = true;

        return $this->remoteScripts;
    }
}

class WatchOpcacheScriptsJobTest extends TestCase
{
    private function setOpcacheInstance(?OPcache $instance): void
    {
        $property = new \ReflectionProperty(OPcache::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, $instance);
    }

    public function testActionWarnsWhenUrlMissing()
    {
        $fake = new FakeOPcache();
        $this->setOpcacheInstance($fake);

        $job = new WatchOpcacheScriptsJobStub();
        $this->callMethod($job, 'loadParameters');
        $this->callMethod($job, 'action');

        $this->assertFalse($fake->remoteCalled);
        $this->assertNotEmpty($job->logs);

        $this->setOpcacheInstance(null);
    }

    public function testActionLogsWhenScriptsFetched()
    {
        $fake = new FakeOPcache();
        $fake->url = Url::parse('https://example.test/scripts');
        $fake->remoteScripts = ['foo.php' => time()];
        $this->setOpcacheInstance($fake);

        $job = new WatchOpcacheScriptsJobStub([
            'timeout' => 1,
            'use_app_url' => 0,
        ]);
        $this->callMethod($job, 'loadParameters');
        $this->callMethod($job, 'action');

        $this->assertTrue($fake->remoteCalled);
        $this->assertNotEmpty($job->logs);
        $this->assertStringContainsString('watch OPcache files', $job->logs[0][1]);

        $this->setOpcacheInstance(null);
    }
}
