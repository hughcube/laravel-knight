<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2024/2/18
 * Time: 14:33.
 */

namespace HughCube\Laravel\Knight\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class Helper
{
    /**
     * @throws RuntimeException
     */
    public function assertClassExists(string $class)
    {
        if (! class_exists($class)) {
            throw new RuntimeException(sprintf("Class '%s' does not exist.", $class));
        }
    }

    /**
     * @throws RuntimeException
     */
    public function assertLoadedExtension(string $name)
    {
        if (! extension_loaded($name)) {
            throw new RuntimeException(sprintf('Not have the %s extension loaded.', $name));
        }
    }

    public static function convertExceptionToArray(Throwable $e): array
    {
        $array = [
            'code' => $e->getCode(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => sprintf('%s(%s)', $e->getFile(), $e->getLine()),
            'trace' => (new Collection($e->getTrace()))->map(fn ($trace) => Arr::except($trace, ['args']))->all(),
        ];

        if ($e instanceof ValidationException) {
            $array['errors'] = $e->errors();
        }

        if (($prev = $e->getPrevious()) !== null) {
            $array['previous'] = static::convertExceptionToArray($prev);
        }

        return $array;
    }
}
