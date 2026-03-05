<?php

declare(strict_types=1);

namespace Roots\Substrate\Console;

use Illuminate\Console\Command;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

class ExecuteToolCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'substrate:execute-tool {tool} {arguments}';

    /**
     * The console command description.
     */
    protected $description = 'Execute a Substrate MCP tool in isolation (internal command)';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Capture any stray output (PHP notices/warnings/deprecations) so it
        // doesn't corrupt the JSON we write to stdout.
        ob_start();

        $toolClassEncoded = (string) $this->argument('tool');
        $argumentsEncoded = (string) $this->argument('arguments');

        // Decode tool class (base64 encoded to avoid shell escaping issues)
        $toolClass = base64_decode($toolClassEncoded, true);

        if ($toolClass === false) {
            $this->outputError("Failed to decode tool class: {$toolClassEncoded}");

            return static::FAILURE;
        }

        // Validate the tool class exists and is a Tool
        if (! class_exists($toolClass) || ! is_subclass_of($toolClass, Tool::class)) {
            $this->outputError("Invalid tool class: {$toolClass}");

            return static::FAILURE;
        }

        // Decode arguments
        $argumentsJson = base64_decode($argumentsEncoded, true);
        if ($argumentsJson === false) {
            $this->outputError("Failed to decode arguments: {$argumentsEncoded}");

            return static::FAILURE;
        }

        $arguments = json_decode($argumentsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->outputError('Invalid arguments format: '.json_last_error_msg());

            return static::FAILURE;
        }

        /** @var Tool $tool */
        $tool = app($toolClass);

        $request = new Request($arguments ?? []);

        try {
            /** @var Response $response */
            $response = $tool->handle($request); // @phpstan-ignore-line
        } catch (Throwable $throwable) {
            $errorResult = Response::error("Tool execution failed (E_THROWABLE): {$throwable->getMessage()}");

            $this->flushAndOutput(json_encode([
                'isError' => true,
                'content' => [
                    $errorResult->content()->toTool($tool),
                ],
            ]));

            return static::FAILURE;
        }

        $this->flushAndOutput(json_encode([
            'isError' => $response->isError(),
            'content' => [
                $response->content()->toTool($tool),
            ],
        ]));

        return static::SUCCESS;
    }

    /**
     * Output an error response as JSON.
     */
    protected function outputError(string $message): void
    {
        $this->flushAndOutput(json_encode([
            'isError' => true,
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
        ]));
    }

    /**
     * Discard any buffered output (PHP notices/warnings) and write clean data to stdout.
     */
    protected function flushAndOutput(string $json): void
    {
        $stray = ob_get_clean();

        if ($stray !== false && $stray !== '') {
            fwrite(STDERR, $stray);
        }

        echo $json;
    }
}
