<?php

namespace HughCube\Laravel\Knight\Http\Actions\OPcache;

use Exception;
use HughCube\Laravel\Knight\Exceptions\UserException;
use HughCube\Laravel\Knight\OPcache\LoadedOPcacheExtension;
use HughCube\Laravel\Knight\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class StatesAction extends Controller
{
    use LoadedOPcacheExtension;

    /**
     * @return array<string, array<string>>
     */
    protected function rules(): array
    {
        return [
            'x-format'  => ['nullable', 'string'],
            'x-as-json' => ['nullable', 'boolean'],
            'x-scripts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return Response
     *
     * @throws Exception
     */
    protected function action(): Response
    {
        if (! extension_loaded('Zend OPcache')) {
            throw new UserException(
                'You do not have the Zend OPcache extension loaded, sample data is being shown instead.'
            );
        }

        $status = opcache_get_status();
        if (false === $status) {
            throw new UserException(
                'Failed to obtain the status of Zend OPcache. Please check whether it is disabled.'
            );
        }

        if ($this->isJson()) {
            $scripts = isset($status['scripts']) && is_array($status['scripts']) ? $status['scripts'] : [];
            $withScripts = 1 == $this->p('x-scripts') || '1' === $this->getRequest()->header('x-scripts');

            $data = [
                'opcache_enabled'     => $status['opcache_enabled'] ?? true,
                'cache_full'          => $status['cache_full'] ?? false,
                'restart_pending'     => $status['restart_pending'] ?? false,
                'restart_in_progress' => $status['restart_in_progress'] ?? false,
                'memory_usage'        => $status['memory_usage'] ?? [],
                'interned_strings'    => $status['interned_strings_usage'] ?? [],
                'statistics'          => $status['opcache_statistics'] ?? [],
                'scripts_count'       => count($scripts),
                'scripts'             => $withScripts ? array_keys($scripts) : null,
            ];

            return $this->asSuccess($data);
        }

        return new Response($this->renderView(dirname(__DIR__, 3).'/OPcache/Views/opcache.php'));
    }

    protected function renderView(string $file): string
    {
        $obLevel = ob_get_level();
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    protected function isJson(): bool
    {
        return 'json' === strtolower(strval($this->p('x-format')))
            || 1 == $this->p('x-as-json')
            || $this->getRequest()->expectsJson();
    }
}
