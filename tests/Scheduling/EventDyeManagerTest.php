<?php

namespace HughCube\Laravel\Knight\Tests\Scheduling;

use HughCube\Laravel\Knight\Scheduling\EventDyeManager;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class EventDyeManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        EventDyeManager::flush();
        parent::tearDown();
    }

    public function testDyeAndShouldRunInQueue()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event1 = $schedule->command('test:simple');
        $event2 = $schedule->command('test:heavy')->runInQueue('heavy_queue', 'redis');

        $this->assertFalse(EventDyeManager::shouldRunInQueue($event1));
        $this->assertTrue(EventDyeManager::shouldRunInQueue($event2));

        $options = EventDyeManager::getQueueOptions($event2);
        $this->assertSame('heavy_queue', $options['queue']);
        $this->assertSame('redis', $options['connection']);
    }

    public function testAliases()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event1 = $schedule->command('test:on-queue')->onQueue('test_queue');
        $event2 = $schedule->command('test:queued')->queued('another_queue');

        $this->assertTrue(EventDyeManager::shouldRunInQueue($event1));
        $this->assertSame('test_queue', EventDyeManager::getQueueOptions($event1)['queue']);

        $this->assertTrue(EventDyeManager::shouldRunInQueue($event2));
        $this->assertSame('another_queue', EventDyeManager::getQueueOptions($event2)['queue']);
    }

    public function testRunInBackgroundCompatibility()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event = $schedule->command('test:bg')->runInBackground();
        $this->assertTrue(EventDyeManager::shouldRunInQueue($event));
    }

    public function testConfigWildcardMatching()
    {
        $this->app['config']->set('knight.schedule.heavy_commands', [
            'order:*',
            'sync:data',
        ]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $event1 = $schedule->command('order:pull');
        $event2 = $schedule->command('sync:data');
        $event3 = $schedule->command('user:info');

        $this->assertTrue(EventDyeManager::shouldRunInQueue($event1));
        $this->assertTrue(EventDyeManager::shouldRunInQueue($event2));
        $this->assertFalse(EventDyeManager::shouldRunInQueue($event3));
    }
}
