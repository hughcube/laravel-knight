<?php

namespace HughCube\Laravel\Knight\OPcache;

require_once __DIR__.'/Support/OpcacheOverrides.php';

use HughCube\Laravel\Knight\Tests\OPcache\OpcacheTestOverrides;

if (! function_exists(__NAMESPACE__.'\extension_loaded')) {
    function extension_loaded($name): bool
    {
        return OpcacheTestOverrides::$extensionLoaded;
    }
}

namespace HughCube\Laravel\Knight\Tests\OPcache;

use Exception;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\Tests\TestCase;

class LoadedOPcacheExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OpcacheTestOverrides::resetDefaults();
    }

    public function testLoadedOPcacheExtensionThrowsWhenMissing()
    {
        $checker = new class()
        {
            use LoadedOPcacheExtension;

            public function callCheck(): void
            {
                $this->loadedOPcacheExtension();
            }
        };

        OpcacheTestOverrides::$extensionLoaded = false;

        $this->expectException(Exception::class);
        $checker->callCheck();
    }

    public function testLoadedOPcacheExtensionPassesWhenLoaded()
    {
        $checker = new class()
        {
            use LoadedOPcacheExtension;

            public function callCheck(): void
            {
                $this->loadedOPcacheExtension();
            }
        };

        OpcacheTestOverrides::$extensionLoaded = true;

        $checker->callCheck();
        $this->assertTrue(true);
    }
}
