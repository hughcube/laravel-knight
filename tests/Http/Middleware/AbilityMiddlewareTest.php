<?php

namespace HughCube\Laravel\Knight\Tests\Http\Middleware;

use HughCube\Laravel\Knight\Http\Middleware\CheckAbilities;
use HughCube\Laravel\Knight\Http\Middleware\CheckForAnyAbility;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;

class AbilityMiddlewareTest extends TestCase
{
    public function testCheckAbilitiesSkipsWhenNoUser()
    {
        $request = Request::create('/abilities', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $middleware = new CheckAbilities();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'ability');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAbilitiesSkipsWhenNoToken()
    {
        $request = Request::create('/abilities', 'GET');
        $request->setUserResolver(function () {
            return new class()
            {
                public function currentAccessToken()
                {
                    return null;
                }
            };
        });

        $middleware = new CheckAbilities();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'ability');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckForAnyAbilitySkipsWhenNoUser()
    {
        $request = Request::create('/abilities', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $middleware = new CheckForAnyAbility();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'ability');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckForAnyAbilitySkipsWhenNoToken()
    {
        $request = Request::create('/abilities', 'GET');
        $request->setUserResolver(function () {
            return new class()
            {
                public function currentAccessToken()
                {
                    return null;
                }
            };
        });

        $middleware = new CheckForAnyAbility();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'ability');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAbilitiesPassesWithAllowedAbility()
    {
        $request = $this->makeRequestWithAbilities(['view']);

        $middleware = new CheckAbilities();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'view');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckAbilitiesThrowsWhenMissingAbility()
    {
        $this->expectException(MissingAbilityException::class);

        $request = $this->makeRequestWithAbilities(['view']);

        $middleware = new CheckAbilities();
        $middleware->handle($request, function () {
            return new Response('ok');
        }, 'edit');
    }

    public function testCheckForAnyAbilityPassesWhenAnyAllowed()
    {
        $request = $this->makeRequestWithAbilities(['edit']);

        $middleware = new CheckForAnyAbility();
        $response = $middleware->handle($request, function () {
            return new Response('ok');
        }, 'view', 'edit');

        $this->assertSame('ok', $response->getContent());
    }

    public function testCheckForAnyAbilityThrowsWhenNoneAllowed()
    {
        $this->expectException(MissingAbilityException::class);

        $request = $this->makeRequestWithAbilities(['view']);

        $middleware = new CheckForAnyAbility();
        $middleware->handle($request, function () {
            return new Response('ok');
        }, 'edit', 'delete');
    }

    private function makeRequestWithAbilities(array $allowed): Request
    {
        $request = Request::create('/abilities', 'GET');
        $request->setUserResolver(function () use ($allowed) {
            return new class($allowed)
            {
                private array $allowed;

                public function __construct(array $allowed)
                {
                    $this->allowed = $allowed;
                }

                public function currentAccessToken()
                {
                    return new \stdClass();
                }

                public function tokenCan(string $ability): bool
                {
                    return in_array($ability, $this->allowed, true);
                }
            };
        });

        return $request;
    }
}
