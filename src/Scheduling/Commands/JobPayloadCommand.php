<?php

namespace HughCube\Laravel\Knight\Scheduling\Commands;

use Exception;
use HughCube\Laravel\Knight\Console\Command;
use Illuminate\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SyncQueue;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class JobPayloadCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:job-payload
                           {job=HughCube\\Laravel\\Knight\\Scheduling\\Jobs\\ScheduleJob : The name of the job class}
                           {--data= : The data for the job constructor in JSON format}
                           {--connection= : The name of the queue connection}
                           {--queue= : The name of the queue}';

    /**
     * @var string
     */
    protected $description = 'Get the queue payload for a job';

    /**
     * @var string[]
     */
    protected $aliases = ['schedule:job-payload'];

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $job = $this->makeJob();
        $queue = $this->getQueue();
        $queueName = $this->option('queue') ?: 'default';

        $reflection = new ReflectionClass(get_class($queue));
        $method = $reflection->getMethod('createPayload');
        $method->setAccessible(true);
        $payload = $method->invokeArgs($queue, [$job, $queueName]);

        $this->info(sprintf('job %s payload:', get_class($job)));
        $this->line($payload);

        return 0;
    }

    /**
     * @return object
     *
     * @throws Exception
     */
    protected function makeJob(): object
    {
        $class = ltrim((string) $this->argument('job'), '\\');

        if (! class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Job class "%s" does not exist.', $class));
        }

        $data = $this->getData();
        if (empty($data)) {
            return new $class();
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        // 索引数组列表：直接解包传参
        if (function_exists('array_is_list') ? array_is_list($data) : array_values($data) === $data) {
            return new $class(...$data);
        }

        // 构造函数只有一个参数且传入关联数组：直接传入整个数组
        if ($constructor->getNumberOfParameters() === 1) {
            return new $class($data);
        }

        // 尝试命名参数解包，失败时降级传入数组
        try {
            return new $class(...$data);
        } catch (Throwable $e) {
            return new $class($data);
        }
    }

    /**
     * @return array|null
     *
     * @throws Exception
     */
    protected function getData(): ?array
    {
        $data = $this->option('data');
        if (empty($data)) {
            return null;
        }

        $data = json_decode((string) $data, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception(json_last_error_msg());
        }

        return $data;
    }

    /**
     * 获取队列实例.
     *
     * @return Queue
     */
    protected function getQueue(): Queue
    {
        $connection = $this->option('connection');

        if ($this->laravel->bound('queue')) {
            /** @var QueueManager $queueManager */
            $queueManager = $this->laravel->make('queue');

            /** @phpstan-ignore-next-line */
            return $queueManager->connection($connection);
        }

        return new SyncQueue();
    }
}
