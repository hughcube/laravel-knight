<?php

namespace HughCube\Laravel\Knight\Tests\Http\Actions\Scheduling;

use HughCube\Laravel\Knight\Http\Actions\Scheduling\ScheduleAction;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ScheduleActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::any('/devops/schedule', ScheduleAction::class)->name('knight.devops.schedule');
    }

    public function testScheduleActionExecutesAndReturnsReport()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $executed = false;
        $schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        $request = Request::create('/devops/schedule', 'POST');
        $this->app->instance('request', $request);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $response = $action();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode(strval($response->getContent()), true);

        $this->assertSame('Success', $data['Code']);
        $this->assertTrue($executed, '调度任务应当由 ScheduleAction 标准派发执行');
        $this->assertArrayHasKey('tasks', $data['Data']);
        $this->assertArrayHasKey('count', $data['Data']);
        $this->assertArrayHasKey('total_duration', $data['Data']);
        $this->assertGreaterThanOrEqual(0.0, $data['Data']['total_duration']);
        $this->assertSame(1, $data['Data']['count']);

        $task = $data['Data']['tasks'][0];
        $this->assertSame('sync', $task['mode']);
        $this->assertArrayHasKey('duration', $task);
        $this->assertArrayHasKey('delays', $task);
        $this->assertGreaterThanOrEqual(0.0, $task['duration']);
        $this->assertGreaterThanOrEqual(0.0, $task['delays']);
    }

    public function testScheduleActionDispatchesScheduledTasksExecutedEvent()
    {
        \Illuminate\Support\Facades\Event::fake([\HughCube\Laravel\Knight\Events\ScheduledTasksExecuted::class]);

        $request = Request::create('/devops/schedule', 'POST');
        $this->app->instance('request', $request);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $action();

        \Illuminate\Support\Facades\Event::assertDispatched(
            \HughCube\Laravel\Knight\Events\ScheduledTasksExecuted::class
        );
    }

    public function testScheduleActionLogsWhenNoTasks()
    {
        $logged = false;
        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->once()
            ->with(null)
            ->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) use (&$logged) {
                $logged = str_contains($message, 'No scheduled commands are ready to run');

                return $logged;
            });

        $request = Request::create('/devops/schedule', 'POST');
        $this->app->instance('request', $request);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $action();

        $this->assertTrue($logged);
    }

    public function testScheduleActionLogsTasksInTwoColumnDetailFormat()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $schedule->call(fn () => null)->everyMinute();

        $loggedMessages = [];
        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->once()
            ->with(null)
            ->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) use (&$loggedMessages) {
                $loggedMessages[] = $message;

                return true;
            });

        $request = Request::create('/devops/schedule', 'POST');
        $this->app->instance('request', $request);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $action();

        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('Running [', $loggedMessages[0]);
        $this->assertStringContainsString('..', $loggedMessages[0]);
        $this->assertStringContainsString('DONE (delays:', $loggedMessages[0]);
        $this->assertStringContainsString('sync)', $loggedMessages[0]);
    }

    public function testScheduleActionDetectsScheduleLogChannelWhenConfigured()
    {
        $this->app['config']->set('logging.channels.schedule', [
            'driver' => 'single',
            'path'   => storage_path('logs/schedule.log'),
        ]);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);

        $reflection = new \ReflectionMethod($action, 'getLogChannel');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        $this->assertSame('schedule', $reflection->invoke($action));
    }

    public function testScheduleActionPushesRunInBackgroundToQueue()
    {
        \Illuminate\Support\Facades\Queue::fake([\HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob::class]);

        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);
        $schedule->exec('echo "Async Task"')->everyMinute()->runInBackground();

        $request = Request::create('/devops/schedule', 'POST');
        $this->app->instance('request', $request);

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $response = $action();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode(strval($response->getContent()), true);

        $this->assertSame('Success', $data['Code']);
        $this->assertArrayHasKey('tasks', $data['Data']);
        \Illuminate\Support\Facades\Queue::assertPushed(\HughCube\Laravel\Knight\Scheduling\Jobs\CommandJob::class);
    }

    public function testScheduleActionLogsQueueWithJobId()
    {
        $loggedMessages = [];
        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->once()
            ->with(null)
            ->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) use (&$loggedMessages) {
                $loggedMessages[] = $message;

                return true;
            });

        $report = [
            'count'          => 1,
            'triggered_at'   => '2026-10-26 16:00:00',
            'total_duration' => 2.50,
            'tasks'          => [
                [
                    'task'         => "'artisan' queue:monitor",
                    'defined_mode' => 'background',
                    'mode'         => 'queue',
                    'status'       => 'pushed',
                    'queue'        => 'default',
                    'job_id'       => '98521',
                    'duration'     => 2.10,
                    'delays'       => 2.10,
                ],
            ],
        ];

        /** @var ScheduleAction $action */
        $action = $this->app->make(ScheduleAction::class);
        $reflection = new \ReflectionMethod($action, 'logReport');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->invoke($action, $report);

        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('queue: default@98521', $loggedMessages[0]);
    }
}
