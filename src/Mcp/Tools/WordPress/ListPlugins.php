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
class ListPlugins extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all installed WordPress plugins with their status (active/inactive), version, and metadata.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by status: "active", "inactive", "must-use", or "all" (default)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        $status = $request->get('status', 'all');

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $muPlugins = function_exists('get_mu_plugins') ? get_mu_plugins() : [];

        $result = [
            'active' => [],
            'inactive' => [],
            'must_use' => [],
        ];

        // Process regular plugins
        foreach ($allPlugins as $pluginFile => $pluginData) {
            $isActive = in_array($pluginFile, $activePlugins, true);
            $category = $isActive ? 'active' : 'inactive';

            if ($status !== 'all' && $status !== $category && $status !== 'must-use') {
                continue;
            }

            $result[$category][$pluginFile] = [
                'file' => $pluginFile,
                'name' => $pluginData['Name'] ?? '',
                'version' => $pluginData['Version'] ?? '',
                'author' => $pluginData['Author'] ?? '',
                'author_uri' => $pluginData['AuthorURI'] ?? '',
                'description' => $pluginData['Description'] ?? '',
                'plugin_uri' => $pluginData['PluginURI'] ?? '',
                'text_domain' => $pluginData['TextDomain'] ?? '',
                'requires_wp' => $pluginData['RequiresWP'] ?? '',
                'requires_php' => $pluginData['RequiresPHP'] ?? '',
                'network' => $pluginData['Network'] ?? false,
            ];
        }

        // Process must-use plugins
        if ($status === 'all' || $status === 'must-use') {
            foreach ($muPlugins as $pluginFile => $pluginData) {
                $result['must_use'][$pluginFile] = [
                    'file' => $pluginFile,
                    'name' => $pluginData['Name'] ?? '',
                    'version' => $pluginData['Version'] ?? '',
                    'author' => $pluginData['Author'] ?? '',
                    'description' => $pluginData['Description'] ?? '',
                ];
            }
        }

        // Filter based on status if not 'all'
        if ($status === 'active') {
            $result = ['active' => $result['active']];
        } elseif ($status === 'inactive') {
            $result = ['inactive' => $result['inactive']];
        } elseif ($status === 'must-use') {
            $result = ['must_use' => $result['must_use']];
        }

        return Response::json([
            'counts' => [
                'active' => count($result['active'] ?? []),
                'inactive' => count($result['inactive'] ?? []),
                'must_use' => count($result['must_use'] ?? []),
            ],
            'plugins' => $result,
        ]);
    }
}
