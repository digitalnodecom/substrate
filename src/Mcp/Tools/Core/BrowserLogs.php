<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Roots\Substrate\Concerns\ReadsLogs;

#[IsReadOnly]
class BrowserLogs extends Tool
{
    use ReadsLogs;

    /**
     * The tool's description.
     */
    protected string $description = 'Read the last N log entries from the browser console log. Very helpful for debugging frontend JavaScript issues.';

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
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $maxEntries = (int) $request->get('entries');

        if ($maxEntries <= 0) {
            return Response::error('The "entries" argument must be greater than 0.');
        }

        $logFile = storage_path('logs/browser.log');

        if (! file_exists($logFile)) {
            return Response::error('No browser log file found. Make sure @substrateJs is included in your layout or the InjectSubstrate middleware is active.');
        }

        $entries = $this->readLastLogEntries($logFile, $maxEntries);

        if ($entries === []) {
            return Response::text('No browser log entries yet.');
        }

        $logs = implode("\n\n", $entries);

        if (empty(trim($logs))) {
            return Response::text('No browser log entries yet.');
        }

        return Response::text($logs);
    }
}
