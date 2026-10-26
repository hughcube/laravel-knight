<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Jobs\CleanFilesJob;
use HughCube\Laravel\Knight\Queue\Jobs\ScheduleJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;

class ScheduleJobTest extends TestCase
{
    public function testRun()
    {
        $this->assertJob(CleanFilesJob::new([
            'items' => [],
        ]));
    }

    public function testIsDue()
    {
        $job = new class() extends ScheduleJob
        {
            protected function testHandler()
            {
                $this->isDue('* * * * *');
            }
        };

        $this->assertJob($job);
    }

    public function testParseDirJobsLoadsJobs()
    {
        $baseDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-jobs-'.uniqid();
        $jobsDir = $baseDir.DIRECTORY_SEPARATOR.'Jobs';
        mkdir($jobsDir, 0777, true);

        // Use realpath to resolve symlinks (e.g., /var -> /private/var on macOS)
        // This ensures $baseDir matches the path returned by SplFileInfo::getRealPath()
        $baseDir = realpath($baseDir);
        $jobsDir = realpath($jobsDir);

        $file = $jobsDir.DIRECTORY_SEPARATOR.'DemoJob.php';
        file_put_contents($file, "<?php\nnamespace Jobs;\nclass DemoJob {}\n");
        require_once $file;

        $job = new ScheduleJob();

        try {
            $jobs = $this->callMethod($job, 'parseDirJobs', ['DemoJob.php', $jobsDir, $baseDir]);
            $this->assertCount(1, $jobs);
            $this->assertInstanceOf(\Jobs\DemoJob::class, $jobs[0]);
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
            if (is_dir($jobsDir)) {
                @rmdir($jobsDir);
            }
            if (is_dir($baseDir)) {
                @rmdir($baseDir);
            }
        }
    }

    public function testPushAndFireJobs()
    {
        $dispatcher = new class() implements Dispatcher
        {
            public array $dispatched = [];
            public array $dispatchedSync = [];

            public function dispatch($job)
            {
                $this->dispatched[] = $job;

                return 'job-id';
            }

            public function dispatchSync($command, $handler = null)
            {
                $this->dispatchedSync[] = $command;

                return 'sync-id';
            }

            public function dispatchNow($command, $handler = null)
            {
                return $this->dispatchSync($command);
            }

            public function hasCommandHandler($command)
            {
                return false;
            }

            public function getCommandHandler($command)
            {
                return null;
            }

            public function pipeThrough(array $pipes)
            {
                return $this;
            }

            public function map(array $map)
            {
                return $this;
            }

            public function dispatchAfterResponse($command, $handler = null)
            {
            }

            public function chain($jobs = null)
            {
                return $this;
            }
        };

        $this->app->instance(Dispatcher::class, $dispatcher);

        $job = new class() extends ScheduleJob
        {
            public array $logs = [];

            public function log($level, string $message, array $context = []): void
            {
                $this->logs[] = [$level, $message];
            }
        };

        $result = $this->callMethod($job, 'pushJob', [new \stdClass()]);
        $this->assertSame('job-id', $result);
        $this->assertSame(1, $this->getScheduleJobCount($job));

        $result = $this->callMethod($job, 'fireJob', [new \stdClass()]);
        $this->assertSame('sync-id', $result);
        $this->assertSame(2, $this->getScheduleJobCount($job));
        $this->assertNotEmpty($job->logs);
    }

    public function testDueHelpers()
    {
        $dispatcher = new class() implements Dispatcher
        {
            public function dispatch($job)
            {
                return 'job-id';
            }

            public function dispatchSync($command, $handler = null)
            {
                return 'sync-id';
            }

            public function dispatchNow($command, $handler = null)
            {
                return $this->dispatchSync($command);
            }

            public function hasCommandHandler($command)
            {
                return false;
            }

            public function getCommandHandler($command)
            {
                return null;
            }

            public function pipeThrough(array $pipes)
            {
                return $this;
            }

            public function map(array $map)
            {
                return $this;
            }

            public function dispatchAfterResponse($command, $handler = null)
            {
            }

            public function chain($jobs = null)
            {
                return $this;
            }
        };

        $this->app->instance(Dispatcher::class, $dispatcher);

        $job = new class() extends ScheduleJob
        {
            protected function getActionStartedAt($share = false): Carbon
            {
                return Carbon::create(2020, 1, 1, 0, 0, 0);
            }
        };

        $this->assertTrue($this->callMethod($job, 'isDue', ['* * * * *']));
        $this->assertFalse($this->callMethod($job, 'isDue', ['0 0 2 1 *']));

        $this->callMethod($job, 'pushJobIfDue', ['* * * * *', new \stdClass()]);
        $this->assertSame(1, $this->getScheduleJobCount($job));

        $this->callMethod($job, 'fireJobIfDue', ['0 0 2 1 *', new \stdClass()]);
        $this->assertSame(1, $this->getScheduleJobCount($job));
    }

    private function getScheduleJobCount(ScheduleJob $job): int
    {
        $property = new \ReflectionProperty(ScheduleJob::class, 'scheduleJobCount');
        $property->setAccessible(true);

        return (int) $property->getValue($job);
    }
}
