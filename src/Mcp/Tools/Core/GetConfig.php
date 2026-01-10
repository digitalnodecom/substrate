<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Config;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetConfig extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get configuration values. Supports Acorn config (dot notation like "app.name"), WordPress options (prefix with "option:" like "option:blogname"), environment variables (prefix with "env:" like "env:WP_DEBUG"), and WordPress constants (prefix with "const:" like "const:WP_HOME").';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema
                ->string()
                ->description('The config key. Use dot notation for Acorn config (e.g., "app.name"), "option:name" for WP options, "env:name" for env vars, "const:name" for constants.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $key = $request->get('key');

        // Check for prefixed retrieval
        if (str_contains($key, ':')) {
            [$prefix, $name] = explode(':', $key, 2);

            $value = match ($prefix) {
                'option' => $this->getOption($name),
                'env' => env($name),
                'const' => defined($name) ? constant($name) : null,
                default => null,
            };

            if ($value === null && $prefix !== 'const') {
                return Response::error("Config key '{$key}' not found.");
            }

            return Response::json([
                'key' => $key,
                'type' => $prefix,
                'value' => $value,
            ]);
        }

        // Default: Acorn/Laravel config
        if (! Config::has($key)) {
            return Response::error("Config key '{$key}' not found.");
        }

        return Response::json([
            'key' => $key,
            'type' => 'config',
            'value' => Config::get($key),
        ]);
    }

    /**
     * Get a WordPress option value.
     *
     * @return mixed
     */
    protected function getOption(string $name)
    {
        if (! function_exists('get_option')) {
            return null;
        }

        return get_option($name);
    }
}
