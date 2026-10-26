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
        $this->assertSame(1, $data['Data']['count']);
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
}
