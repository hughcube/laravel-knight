<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class SystemAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        return $this->asSuccess([
            'os' => php_uname(),
            'php_version' => PHP_VERSION,
            'current_memory_usage' => $this->asFileSize(memory_get_usage()),
            'peak_memory_usage' => $this->asFileSize(memory_get_peak_usage()),
            'php_extensions' => get_loaded_extensions(),
        ]);
    }

    protected function asFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
