<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Roots\Substrate\Concerns\DetectsProjectRoot;
use Roots\Substrate\Concerns\ReadsLogs;

#[IsReadOnly]
class LastError extends Tool
{
    use DetectsProjectRoot;
    use ReadsLogs;

    /**
     * The tool's description.
     */
    protected string $description = 'Get details of the last error/exception from the application logs. Searches both WordPress debug.log and Acorn logs. Use the browser-logs tool for browser/JavaScript errors.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // First, try to get cached error from runtime
        $cached = Cache::get('substrate:last_error');
        if ($cached) {
            $entry = "[{$cached['timestamp']}] {$cached['level']}: {$cached['message']}";
            if (! empty($cached['context'])) {
                $entry .= ' '.json_encode($cached['context']);
            }

            return Response::text($entry);
        }

        // Try to find error in log files
        $logSources = [
            'acorn' => storage_path('logs/laravel.log'),
            'wordpress' => defined('WP_CONTENT_DIR')
                ? WP_CONTENT_DIR.'/debug.log'
                : $this->projectRoot().'/web/wp/wp-content/debug.log',
        ];

        foreach ($logSources as $source => $logFile) {
            if (! file_exists($logFile)) {
                continue;
            }

            $entry = $this->readLastErrorEntry($logFile);

            if ($entry !== null) {
                return Response::text("[Source: {$source}]\n{$entry}");
            }
        }

        return Response::error('No error entries found in the inspected log files.');
    }
}
