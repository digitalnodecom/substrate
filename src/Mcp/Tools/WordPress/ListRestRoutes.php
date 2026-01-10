<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRestRoutes extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress REST API endpoints with their methods, namespaces, and arguments.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'namespace' => $schema->string()
                ->description('Filter by namespace (e.g., "wp/v2", "wc/v3", "custom/v1")'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('rest_get_server')) {
            return Response::error('WordPress REST API not available.');
        }

        $namespaceFilter = $request->get('namespace');

        // Load admin classes needed for some REST endpoints (like Site Health)
        if (! class_exists('WP_Site_Health') && defined('ABSPATH')) {
            $siteHealthFile = ABSPATH.'wp-admin/includes/class-wp-site-health.php';
            if (file_exists($siteHealthFile)) {
                require_once $siteHealthFile;
            }
        }

        // Ensure REST API is initialized
        try {
            if (! did_action('rest_api_init')) {
                do_action('rest_api_init');
            }

            $server = rest_get_server();
            $routes = $server->get_routes();
            $namespaces = $server->get_namespaces();
        } catch (\Throwable $e) {
            return Response::error('Failed to initialize REST API: '.$e->getMessage());
        }

        $result = [];

        foreach ($routes as $route => $handlers) {
            // Extract namespace from route
            $routeNamespace = $this->extractNamespace($route, $namespaces);

            // Apply namespace filter
            if ($namespaceFilter && $routeNamespace !== $namespaceFilter) {
                continue;
            }

            $endpoints = [];
            foreach ($handlers as $handler) {
                $endpoints[] = [
                    'methods' => array_keys(array_filter($handler['methods'] ?? [])),
                    'args' => $this->formatArgs($handler['args'] ?? []),
                    'permission_callback' => isset($handler['permission_callback']),
                ];
            }

            $result[$route] = [
                'route' => $route,
                'namespace' => $routeNamespace,
                'endpoints' => $endpoints,
            ];
        }

        return Response::json([
            'namespaces' => $namespaces,
            'count' => count($result),
            'routes' => $result,
        ]);
    }

    /**
     * Extract namespace from route.
     *
     * @param  array<string>  $namespaces
     */
    protected function extractNamespace(string $route, array $namespaces): string
    {
        foreach ($namespaces as $namespace) {
            if (str_starts_with($route, '/'.$namespace)) {
                return $namespace;
            }
        }

        return 'unknown';
    }

    /**
     * Format endpoint arguments.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function formatArgs(array $args): array
    {
        $formatted = [];

        foreach ($args as $name => $config) {
            $formatted[$name] = [
                'type' => $config['type'] ?? 'string',
                'required' => $config['required'] ?? false,
                'description' => $config['description'] ?? '',
                'default' => $config['default'] ?? null,
            ];
        }

        return $formatted;
    }
}
