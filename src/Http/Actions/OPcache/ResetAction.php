<?php

namespace HughCube\Laravel\Knight\Http\Actions\OPcache;

use Exception;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResetAction extends Controller
{
    use LoadedOPcacheExtension;

    /**
     * @throws Exception
     */
    protected function action(): Response
    {
        $startTime = microtime(true);
        $request = $this->getRequest();

        $this->loadedOPcacheExtension();

        if (! opcache_reset()) {
            throw new Exception('Failed to reset OPcache.');
        }

        $duration = microtime(true) - $startTime;

        Log::info(sprintf(
            'OPcache reset - IP: %s, User-Agent: %s, Duration: %sms, URL: %s',
            $request->ip(),
            $request->userAgent(),
            round($duration * 1000, 2),
            $request->fullUrl()
        ));

        return $this->asSuccess();
    }
}
