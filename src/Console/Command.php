<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2023/3/21
 * Time: 13:25.
 */

namespace HughCube\Laravel\Knight\Console;

use HughCube\Laravel\Knight\Support\Str;
use HughCube\Laravel\Knight\Traits\Container;
use HughCube\Laravel\Knight\Traits\GetOrSet;
use Illuminate\Support\Collection;

class Command extends \Illuminate\Console\Command
{
    use Container;
    use GetOrSet;

    public function __construct()
    {
        parent::__construct();

        if (property_exists($this, 'aliases') && ! empty($this->aliases)) {
            $this->setAliases($this->aliases);
        }
    }

    protected function getOrAskOption($name, $question, $default = null)
    {
        $value = $this->option($name);
        if (! empty($value)) {
            return $value;
        }

        return $this->ask($question, $default);
    }

    protected function getOrAskOptionIds($name, $question, $default = null): Collection
    {
        $ids = $this->getOrAskOption($name, $question, $default);

        return Collection::wrap(explode(',', $ids))->values();
    }

    protected function getOrAskBoolOption($name, $question, $default = false): bool
    {
        $value = $this->option($name);
        if (null !== $value && '' !== $value) {
            return Str::isTrue($value);
        }

        return $this->confirm($question, $default);
    }
}
