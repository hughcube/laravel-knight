<?php

namespace HughCube\Laravel\Knight\Tests\Http;

use HughCube\Laravel\Knight\Tests\TestCase;

class LumenRequestTest extends TestCase
{
    public function testCreateReturnsLumenRequestWhenAvailable()
    {
        if (! class_exists(\Laravel\Lumen\Http\Request::class)) {
            $this->markTestSkipped('Lumen Request class is not available.');
        }

        $request = \HughCube\Laravel\Knight\Http\LumenRequest::create('/hello', 'GET');

        $this->assertInstanceOf(\Laravel\Lumen\Http\Request::class, $request);
    }
}
