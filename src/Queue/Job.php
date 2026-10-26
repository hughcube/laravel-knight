<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/7
 * Time: 11:27 上午.
 */

namespace HughCube\Laravel\Knight\Queue;

use BadMethodCallException;
use Exception;
use HughCube\Base\Base;
use HughCube\Laravel\Knight\Contracts\Queue\FromFlowJob;
use HughCube\Laravel\Knight\Events\ActionProcessed;
use HughCube\Laravel\Knight\Events\ActionProcessing;
use HughCube\Laravel\Knight\Support\ParameterBag;
use HughCube\Laravel\Knight\Traits\Container;
use HughCube\Laravel\Knight\Traits\GetOrSet;
use HughCube\Laravel\Knight\Traits\Logger;
use HughCube\Laravel\Knight\Traits\ParameterBag as ParameterBagTrait;
use HughCube\Laravel\Knight\Traits\Validation;
use HughCube\StaticInstanceInterface;
use HughCube\StaticInstanceTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @method static PendingDispatch|static dispatch(...$arguments)
 * @method static PendingDispatch|Fluent|static dispatchIf($boolean, ...$arguments)
 * @method static PendingDispatch|Fluent|static dispatchUnless($boolean, ...$arguments)
 */
abstract class Job implements ShouldQueue, StaticInstanceInterface, FromFlowJob
{
    use Logger;
    use GetOrSet;
    use Queueable;
    use Container;
    use Validation;
    use Dispatchable;
    use ParameterBagTrait;
    use InteractsWithQueue;
    use StaticInstanceTrait;

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var array|string|null
     */
    protected $logChannel = null;

    /**
     * @var null|FlowJobDescribe
     */
    protected ?FlowJobDescribe $flowJobDescribe = null;

    /**
     * @var float|null
     */
    protected $dispatchedAt = null;

    /**
     * @var int
     */
    protected static int $__jobCounter = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->dispatchedAt = microtime(true);

        static::$__jobCounter++;
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function handle(): void
    {
        // Log the time of entry in the job logic
        $this->getActionStartedAt(true);

        // Prepare the pid of the job
        $this->getPid();

        // Collect all validated parameters
        $this->loadParameters();

        $this->getEventsDispatcher()->dispatch(new ActionProcessing($this));

        try {
            $this->beforeAction();
            $this->action();
        } finally {
            $this->afterAction();
        }
        $this->getEventsDispatcher()->dispatch(new ActionProcessed($this));
    }

    abstract protected function action(): void;

    protected function beforeAction()
    {
    }

    protected function afterAction()
    {
    }

    protected function getActionStartedAt($share = false): Carbon
    {
        /** @var Carbon $dateTime */
        $dateTime = $this->getOrSet(__METHOD__, function () {
            return Carbon::now();
        });

        return $share ? $dateTime : $dateTime->clone();
    }

    /**
     * @deprecated
     */
    protected function getJobStartedAt($share = false): Carbon
    {
        return $this->getActionStartedAt($share);
    }

    /**
     * @throws Exception
     */
    protected function getPid(): string
    {
        return $this->getOrSet(__METHOD__, function () {
            $hostname = base_convert(Base::toString(abs(crc32(gethostname()))), 10, 36);
            $pid = base_convert(Base::toString(getmypid()), 10, 36);
            $counter = base_convert(Base::toString(static::$__jobCounter), 10, 36);

            return sprintf('%s-%s-%s', $hostname, $pid, $counter);
        });
    }

    protected function getDelays(): float
    {
        return round($this->getActionStartedAt(true)->diffInMicroseconds(Carbon::now()) / 1000, 2);
    }

    /**
     * @return float|null
     */
    public function getQueueWaitMs()
    {
        if (null === $this->dispatchedAt) {
            return null;
        }

        $startedAt = (float) $this->getActionStartedAt(true)->format('U.u');

        return round(($startedAt - $this->dispatchedAt) * 1000, 2);
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     *
     * @throws
     */
    protected function loadParameters()
    {
        if ($this->parameterBag instanceof ParameterBag) {
            return;
        }

        $this->parameterBag = new ParameterBag($this->validate($this->getData()));
    }

    protected function getSerializeData(): string
    {
        return serialize($this->data);
    }

    protected function getJsonData(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->data, $flags);
    }

    protected function getValidData(): array
    {
        return $this->p()->all();
    }

    protected function getSerializeValidData(): string
    {
        return serialize($this->getValidData());
    }

    protected function getJsonValidData(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->getValidData(), $flags);
    }

    protected function getName(?object $job = null): string
    {
        return Str::afterLast(get_class($job ?? $this), '\\');
    }

    /**
     * @return array|string|null
     */
    public function getLogChannel()
    {
        return $this->logChannel;
    }

    /**
     * @param  array|string|null  $channel
     * @return $this
     */
    public function setLogChannel($channel = null): Job
    {
        $this->logChannel = $channel;

        return $this;
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function log($level, string $message, array $context = [])
    {
        $message = sprintf('%s [%s] [%.2fms] %s', $this->getName(), $this->getPid(), $this->getDelays(), $message);

        Log::channel($this->getLogChannel())->log($level, $message, $context);
    }

    public function setFlowJobDescribe(FlowJobDescribe $describe)
    {
        $this->flowJobDescribe = $describe;
    }

    public function isDelayDeleteFlowJob(): bool
    {
        return false;
    }

    /**
     * @param  string  $key
     * @param  null  $default
     * @return mixed
     *
     * @deprecated Will be removed in a future version.
     */
    protected function get(string $key, $default = null)
    {
        return Arr::get($this->getValidData(), $key, $default);
    }

    /**
     * @param  mixed  $key
     * @return bool
     *
     * @deprecated Will be removed in a future version.
     */
    protected function has($key): bool
    {
        return Arr::has($this->getValidData(), $key);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     *
     * @deprecated Will be removed in a future version.
     */
    protected function set(string $key, $value)
    {
        $this->p()->set($key, $value);

        return $this;
    }

    /**
     * 同步执行 Job，日志输出到 stdout（适用于 artisan 命令行场景）.
     *
     * @param  array<int|string, mixed>  ...$arguments
     * @return void
     */
    public static function dispatchStdout(...$arguments): void
    {
        /** @phpstan-ignore new.static */
        $job = new static(...$arguments);
        $job->setLogChannel('stdout');

        $job->getDispatcher()->dispatchSync($job);
    }

    /**
     * @return false|mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->p(), $name)) {
            return call_user_func_array([$this->p(), $name], $arguments);
        }

        throw new BadMethodCallException("No such method exists: {$name}");
    }
}
