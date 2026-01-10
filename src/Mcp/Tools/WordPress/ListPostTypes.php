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
class ListPostTypes extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress post types with their configuration, including custom post types from themes and plugins.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by type: "public", "private", "builtin", "custom", or "all" (default)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('get_post_types')) {
            return Response::error('WordPress post type functions not available.');
        }

        $filter = $request->get('type', 'all');

        $args = match ($filter) {
            'public' => ['public' => true],
            'private' => ['public' => false],
            'builtin' => ['_builtin' => true],
            'custom' => ['_builtin' => false],
            default => [],
        };

        $postTypes = get_post_types($args, 'objects');
        $result = [];

        foreach ($postTypes as $postType) {
            $result[$postType->name] = [
                'name' => $postType->name,
                'label' => $postType->label,
                'singular_label' => $postType->labels->singular_name ?? $postType->label,
                'description' => $postType->description,
                'public' => $postType->public,
                'hierarchical' => $postType->hierarchical,
                'has_archive' => $postType->has_archive,
                'show_in_rest' => $postType->show_in_rest,
                'rest_base' => $postType->rest_base ?: $postType->name,
                'supports' => get_all_post_type_supports($postType->name),
                'taxonomies' => get_object_taxonomies($postType->name),
                'menu_icon' => $postType->menu_icon,
                'menu_position' => $postType->menu_position,
                'capability_type' => $postType->capability_type,
                'rewrite' => $postType->rewrite,
                'builtin' => $postType->_builtin,
            ];
        }

        return Response::json([
            'count' => count($result),
            'post_types' => $result,
        ]);
    }
}
