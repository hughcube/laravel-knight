<?php

if (! function_exists('log_path')) {
    /**
     * Get the path to the log folder.
     *
     * @param  string  $path
     * @return string
     */
    function log_path(string $path = ''): string
    {
        $logPath = config('logging.path') ?: env('LOG_PATH') ?: config('app.log_path') ?: storage_path('logs');

        if (empty($path)) {
            return $logPath;
        }

        return rtrim($logPath, '/').DIRECTORY_SEPARATOR.ltrim($path, '/');
    }
}
