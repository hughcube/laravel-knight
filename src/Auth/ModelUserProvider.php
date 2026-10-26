<?php

namespace HughCube\Laravel\Knight\Auth;

use HughCube\Laravel\Knight\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class ModelUserProvider implements UserProvider
{
    /**
     * The Eloquent user model class.
     *
     * @var class-string<Model>
     */
    protected string $model;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * @param  $method
     * @param  ...$args
     * @return mixed
     */
    protected function callModelMethod($method, ...$args)
    {
        return call_user_func_array([$this->model, $method], $args);
    }

    /**
     * @inheritDoc
     */
    public function retrieveById($identifier)
    {
        return $this->callModelMethod(__FUNCTION__, $identifier);
    }

    /**
     * @inheritDoc
     */
    public function retrieveByToken($identifier, $token)
    {
        return $this->callModelMethod(__FUNCTION__, $identifier, $token);
    }

    /**
     * @inheritDoc
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        /** @phpstan-ignore-next-line */
        return $this->callModelMethod(__FUNCTION__, $user, $token);
    }

    /**
     * @inheritDoc
     */
    public function retrieveByCredentials(array $credentials)
    {
        return $this->callModelMethod(__FUNCTION__, $credentials);
    }

    /**
     * @inheritDoc
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return $this->callModelMethod(__FUNCTION__, $user, $credentials);
    }

    /**
     * @inheritDoc
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        $this->callModelMethod(__FUNCTION__, $user, $credentials, $force);
    }
}
