<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/3/11
 * Time: 19:08.
 */

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Config\Repository;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Queue\QueueManager;

trait Container
{
    /**
     * @return IlluminateContainer
     */
    protected function getContainer(): IlluminateContainer
    {
        return IlluminateContainer::getInstance();
    }

    /**
     * @return Dispatcher
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getDispatcher(): Dispatcher
    {
        return $this->getContainer()->make(Dispatcher::class);
    }

    /**
     * @return EventsDispatcher
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getEventsDispatcher(): EventsDispatcher
    {
        return $this->getContainer()->make(EventsDispatcher::class);
    }

    /**
     * @return QueueManager
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getQueueManager(): QueueManager
    {
        return $this->getContainer()->make(QueueManager::class);
    }

    /**
     * @return ExceptionHandler
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getExceptionHandler(): ExceptionHandler
    {
        return $this->getContainer()->make(ExceptionHandler::class);
    }

    /**
     * @return Repository|mixed|null
     *
     * @throws
     *
     * @phpstan-ignore-next-line
     */
    protected function getContainerConfig($key = null, $default = null)
    {
        /** @var Repository $config */
        $config = $this->getContainer()->make('config');

        if (null === $key) {
            return $config;
        }

        return $config->get($key, $default);
    }

    protected function isContainerDebug(): bool
    {
        return true == $this->getContainerConfig('app.debug');
    }

    protected function getContainerEnv(): string
    {
        return strval($this->getContainerConfig('app.env', 'production'));
    }

    protected function isContainerEnv($env): bool
    {
        return $env === $this->getContainerEnv();
    }

    protected function isContainerLocalEnv(): bool
    {
        return $this->isContainerEnv('local');
    }

    protected function isContainerTestEnv(): bool
    {
        return $this->isContainerEnv('testing');
    }

    protected function isContainerProdEnv(): bool
    {
        return $this->isContainerEnv('production');
    }
}
