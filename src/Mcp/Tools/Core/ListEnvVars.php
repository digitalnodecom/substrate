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

#[IsReadOnly]
class ListEnvVars extends Tool
{
    use DetectsProjectRoot;
    /**
     * The tool's description.
     */
    protected string $description = 'List all available environment variable names from a .env file. Returns only the variable names, not their values (for security).';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()
                ->description('The name of the .env file to read (e.g., .env, .env.example). Defaults to .env if not provided.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $filename = $request->get('filename', '.env');

        $filePath = $this->projectRoot().'/'.$filename;

        // Security check: only allow .env files
        if (! str_contains($filename, '.env')) {
            return Response::error('This tool can only read .env files');
        }

        if (! file_exists($filePath)) {
            return Response::error("File not found at '{$filePath}'");
        }

        $envLines = file_get_contents($filePath);

        if (! $envLines) {
            return Response::error('Failed to read .env file.');
        }

        // Parse environment variable names (ignore comments and empty lines)
        $count = preg_match_all('/^(?!\s*#)\s*([^=\s]+)=/m', $envLines, $matches);

        if (! $count) {
            return Response::error('No environment variables found in file.');
        }

        $envVars = array_map('trim', $matches[1]);
        sort($envVars);

        return Response::json([
            'file' => $filename,
            'variables' => $envVars,
            'count' => count($envVars),
        ]);
    }
}
