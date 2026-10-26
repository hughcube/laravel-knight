<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/6/7
 * Time: 1:57 下午.
 */

namespace HughCube\Laravel\Knight\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait Validation
{
    /**
     * Request rules.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * @param  array|Request  $request
     * @return array
     *
     * @throws ValidationException
     * @throws BindingResolutionException
     */
    protected function validate($request): array
    {
        if (empty($rules = $this->rules())) {
            return [];
        }

        /** @phpstan-ignore-next-line */
        $container = method_exists($this, 'getContainer') ? $this->getContainer() : app();

        /** @var Factory $factory */
        $factory = $container->make(Factory::class);

        $validator = $factory->make(
            $request instanceof Request ? $request->all() : $request,
            $rules
        );

        /** @var array|null $data */
        $data = $validator->validate();

        /** Compatible with php7.0 */
        if (null === $data && method_exists($validator, 'valid')) {
            $data = $validator->valid();
        }

        return (empty($data) || ! is_array($data)) ? [] : $data;
    }
}
