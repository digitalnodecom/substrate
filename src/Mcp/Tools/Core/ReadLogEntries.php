<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Roots\Substrate\Concerns\DetectsProjectRoot;
use Roots\Substrate\Concerns\ReadsLogs;

#[IsReadOnly]
class ReadLogEntries extends Tool
{
    use DetectsProjectRoot;
    use ReadsLogs;

    /**
     * The tool's description.
     */
    protected string $description = 'Read the last N log entries from the application log. Handles both WordPress debug.log and Acorn/Laravel log formats. Correctly parses multi-line PSR-3 formatted logs.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entries' => $schema->integer()
                ->description('Number of log entries to return.')
                ->required(),
            'source' => $schema->string()
                ->description('Log source: "auto" (default), "wordpress" (debug.log), or "acorn" (laravel.log).'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $maxEntries = (int) $request->get('entries');
        $source = $request->get('source', 'auto');

        if ($maxEntries <= 0) {
            return Response::error('The "entries" argument must be greater than 0.');
        }

        $logFile = $this->resolveLogFileBySource($source);

        if (! file_exists($logFile)) {
            return Response::error("Log file not found at {$logFile}");
        }

        $entries = $this->readLastLogEntries($logFile, $maxEntries);

        if ($entries === []) {
            return Response::text('Unable to retrieve log entries, or no entries yet.');
        }

        $logs = implode("\n\n", $entries);

        if (empty(trim($logs))) {
            return Response::text('No log entries yet.');
        }

        return Response::text($logs);
    }

    /**
     * Resolve log file path by source.
     */
    protected function resolveLogFileBySource(string $source): string
    {
        return match ($source) {
            'wordpress' => defined('WP_CONTENT_DIR')
                ? WP_CONTENT_DIR.'/debug.log'
                : $this->projectRoot().'/web/wp/wp-content/debug.log',
            'acorn' => storage_path('logs/laravel.log'),
            'browser' => storage_path('logs/browser.log'),
            default => $this->resolveLogFilePath(),
        };
    }
}
