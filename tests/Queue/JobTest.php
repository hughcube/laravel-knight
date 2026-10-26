<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/7
 * Time: 2:12 下午.
 */

namespace HughCube\Laravel\Knight\Tests\Queue;

use BadMethodCallException;
use Closure;
use Exception;
use HughCube\Laravel\Knight\Queue\Job;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Output\NullOutput;

class JobTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testWriteLog()
    {
        /** @var Job $job */
        $job = new class() extends Job
        {
            protected function action(): void
            {
            }
        };

        $getOutput = Closure::bind(function () {
            /** @var Job $this */
            return app()->make(NullOutput::class);
        }, $job, Job::class);

        $uuid = md5(random_bytes(100));
        $getOutput()->writeln("<info>$uuid</info>");

        $this->assertTrue(true);
    }

    public function testDataHelpersAndParameterAccess()
    {
        $job = new class(['name' => 'Ada', 'count' => 1]) extends Job
        {
            protected function rules(): array
            {
                return [
                    'name' => ['string'],
                    'count' => ['integer'],
                ];
            }

            protected function action(): void
            {
            }
        };

        $this->assertSame(serialize(['name' => 'Ada', 'count' => 1]), $this->callMethod($job, 'getSerializeData'));
        $this->assertSame(
            json_encode(['name' => 'Ada', 'count' => 1], JSON_UNESCAPED_UNICODE),
            $this->callMethod($job, 'getJsonData', [JSON_UNESCAPED_UNICODE])
        );

        $this->callMethod($job, 'loadParameters');

        $this->assertSame(['name' => 'Ada', 'count' => 1], $this->callMethod($job, 'getValidData'));
        $this->assertSame(
            serialize(['name' => 'Ada', 'count' => 1]),
            $this->callMethod($job, 'getSerializeValidData')
        );
        $this->assertSame(
            json_encode(['name' => 'Ada', 'count' => 1], JSON_UNESCAPED_UNICODE),
            $this->callMethod($job, 'getJsonValidData', [JSON_UNESCAPED_UNICODE])
        );

        $this->assertSame('stdClass', $this->callMethod($job, 'getName', [new \stdClass()]));

        $job->setLogChannel('stdout');
        $this->assertSame('stdout', $job->getLogChannel());

        $this->assertSame(['name' => 'Ada', 'count' => 1], $job->all());
        $this->assertSame('Ada', $this->callMethod($job, 'get', ['name']));
        $this->assertTrue($this->callMethod($job, 'has', ['count']));

        $this->callMethod($job, 'set', ['name', 'Grace']);
        $this->assertSame('Grace', $this->callMethod($job, 'get', ['name']));
    }

    public function testActionTimingAndDelays()
    {
        $job = new class() extends Job
        {
            protected function action(): void
            {
            }
        };

        $started = $this->callMethod($job, 'getActionStartedAt', [true]);

        $this->assertInstanceOf(Carbon::class, $started);

        usleep(1000);

        $delay = $this->callMethod($job, 'getDelays');

        $this->assertIsFloat($delay);
        $this->assertGreaterThanOrEqual(0.0, $delay);
    }

    public function testCallThrowsWhenMethodMissing()
    {
        $this->expectException(BadMethodCallException::class);

        $job = new class() extends Job
        {
            protected function action(): void
            {
            }
        };

        $job->missingMethod();
    }
}
