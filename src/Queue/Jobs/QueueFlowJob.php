<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/6/21
 * Time: 22:06.
 */

namespace HughCube\Laravel\Knight\Queue\Jobs;

use HughCube\Laravel\Knight\Contracts\Queue\FromFlowJob;
use HughCube\Laravel\Knight\Queue\FlowJobDescribe;
use HughCube\Laravel\Knight\Queue\Job;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class QueueFlowJob extends Job
{
    public function rules(): array
    {
        return [
            'from_connection' => ['required', 'string'],
            'from_queue' => ['string', 'min:1', 'nullable'],

            'to_connection' => ['required', 'string'],
            'to_queue' => ['string', 'min:1', 'nullable'],

            'max_time' => ['integer', 'min:1', 'nullable'],
        ];
    }

    /**
     * @throws BindingResolutionException
     * @throws Throwable
     */
    protected function action(): void
    {
        $startDate = Carbon::now();
        $maxTime = $this->getMaxTime();

        $ids = Collection::empty();
        while ($maxTime >= $startDate->diffInSeconds(Carbon::now())) {
            if (empty($id = $this->nextAvailableJob())) {
                break;
            }
            $ids = $ids->add($id);
        }

        $this->info(sprintf('推送任务%s个, ids: %s', $ids->count(), $ids->implode(', ')));
    }

    /**
     * @throws Throwable
     * @throws BindingResolutionException
     */
    protected function nextAvailableJob(): ?string
    {
        /** @var DatabaseQueue $connection */
        $connection = $this->getQueueManager()->connection($this->p('from_connection'));
        $queue = $connection->getQueue($this->p('from_queue'));

        $fromJob = $connection->pop($queue);
        if (null == $fromJob) {
            return null;
        }

        $job = $this->parseJob($fromJob->getRawBody());

        if ($job instanceof FromFlowJob) {
            $describe = new FlowJobDescribe($connection->getConnectionName(), $queue, $fromJob->getJobId());
            $job->setFlowJobDescribe($describe);
        }

        if (! $job instanceof FromFlowJob || ! $job->isDelayDeleteFlowJob()) {
            $fromJob->delete();
        }

        $connection = $this->getQueueManager()->connection($this->p('to_connection'));

        return $connection->push($job, '', $this->p('to_queue'));
    }

    protected function parseJob($payload)
    {
        return unserialize(json_decode($payload, true)['data']['command']);
    }

    /**
     * @return int
     */
    protected function getMaxTime(): int
    {
        return $this->p('max_time', 60);
    }
}
