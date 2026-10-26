<?php

namespace HughCube\Laravel\Knight\Http\Actions\Devops;

use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class VersionAction extends Controller
{
    /**
     * @return Response
     */
    protected function action(): Response
    {
        $file = base_path('version.json');
        $version = [];

        if (is_file($file) && false !== ($content = @file_get_contents($file))) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $version = $decoded;
            }
        }

        return $this->asSuccess([
            'build_version' => $version['build_version'] ?? env('APP_BUILD_VERSION', 'dev'),
            'build_time' => $version['build_time'] ?? env('APP_BUILD_TIME'),
        ]);
    }
}
