<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/10/28
 * Time: 11:34.
 */

namespace HughCube\Laravel\Knight\Database\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class LogQuerySql
{
    public function handle($event): void
    {
        if (! $this->isEnable()) {
            return;
        }

        if (! $event instanceof QueryExecuted || empty($event->sql)) {
            return;
        }

        if ($event->time < $this->getThreshold()) {
            return;
        }

        Log::log(
            $this->getLogLevel(),
            $this->getLogPrefix()
            .sprintf(', connection: %s, duration: %sms', $event->connectionName, $event->time)
            .sprintf(', sql: %s', $this->replaceBindings($event->sql, $event->bindings))
        );
    }

    /**
     * 慢查询阈值（毫秒），默认 0 表示记录所有查询.
     *
     * @return float
     */
    protected function getThreshold(): float
    {
        return 0;
    }

    /**
     * @return string
     */
    protected function getLogLevel(): string
    {
        return 'debug';
    }

    /**
     * @return string
     */
    protected function getLogPrefix(): string
    {
        return 'query executed';
    }

    /**
     * @param  string  $sql
     * @param  array  $bindings
     * @return string
     */
    protected function replaceBindings(string $sql, array $bindings): string
    {
        $parts = explode('?', $sql);
        $result = $parts[0];

        foreach ($bindings as $i => $binding) {
            if (! isset($parts[$i + 1])) {
                break;
            }

            if (null === $binding) {
                $value = 'null';
            } elseif (is_bool($binding)) {
                $value = $binding ? 'true' : 'false';
            } elseif (is_string($binding)) {
                $value = "'".addslashes($binding)."'";
            } else {
                $value = (string) $binding;
            }

            $result .= $value.$parts[$i + 1];
        }

        return $result;
    }

    protected function isEnable(): bool
    {
        return true;
    }
}
