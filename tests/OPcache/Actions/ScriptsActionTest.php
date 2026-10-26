<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/4/15
 * Time: 8:42 下午.
 */

namespace HughCube\Laravel\Knight\Tests\OPcache\Actions;

use HughCube\Laravel\Knight\OPcache\Actions\ScriptsAction;
use HughCube\Laravel\Knight\OPcache\OPcache;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use ReflectionProperty;

class ScriptsActionTest extends TestCase
{
    public function testRun()
    {
        if (! extension_loaded('Zend OPcache') || ! function_exists('opcache_reset')) {
            $this->markTestSkipped('OPcache extension is not loaded or opcache_reset function is not available');

            return;
        }

        if (! opcache_reset()) {
            $this->markTestSkipped('Failed to reset OPcache');

            return;
        }

        /** @var ScriptsAction $action */
        $action = $this->app->make(ScriptsAction::class);

        $response = $action();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertJson($response->content());

        $payload = $response->getData(true);

        $this->assertArrayHasKey('Code', $payload);
        $this->assertSame('Success', Arr::get($payload, 'Code'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsArray(Arr::get($payload, 'Data.scripts'));
    }

    public function testActionUsesStubbedOpcacheInstance()
    {
        $property = new ReflectionProperty(OPcache::class, 'instance');
        $property->setAccessible(true);
        $previous = $property->getValue();

        $stub = new class() extends OPcache
        {
            public function getScripts(): array
            {
                return [
                    'a.php' => time(),
                    'b.php' => time(),
                ];
            }
        };

        $property->setValue(null, $stub);

        try {
            $action = new ScriptsAction();
            $response = $this->callMethod($action, 'action');

            $this->assertInstanceOf(JsonResponse::class, $response);
            $data = $response->getData(true);
            $this->assertSame('Success', Arr::get($data, 'Code'));
            $this->assertSame(2, Arr::get($data, 'Data.count'));
        } finally {
            $property->setValue(null, $previous);
        }
    }
}
