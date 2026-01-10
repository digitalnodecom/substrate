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
class ListTaxonomies extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress taxonomies with their configuration, including custom taxonomies from themes and plugins.';

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
            'post_type' => $schema->string()
                ->description('Filter by associated post type (e.g., "post", "product")'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('get_taxonomies')) {
            return Response::error('WordPress taxonomy functions not available.');
        }

        $filter = $request->get('type', 'all');
        $postType = $request->get('post_type');

        $args = match ($filter) {
            'public' => ['public' => true],
            'private' => ['public' => false],
            'builtin' => ['_builtin' => true],
            'custom' => ['_builtin' => false],
            default => [],
        };

        if ($postType) {
            $args['object_type'] = [$postType];
        }

        $taxonomies = get_taxonomies($args, 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $termCount = wp_count_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);

            $result[$taxonomy->name] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'singular_label' => $taxonomy->labels->singular_name ?? $taxonomy->label,
                'description' => $taxonomy->description,
                'public' => $taxonomy->public,
                'hierarchical' => $taxonomy->hierarchical,
                'show_in_rest' => $taxonomy->show_in_rest,
                'rest_base' => $taxonomy->rest_base ?: $taxonomy->name,
                'object_types' => $taxonomy->object_type,
                'term_count' => is_wp_error($termCount) ? 0 : (int) $termCount,
                'rewrite' => $taxonomy->rewrite,
                'builtin' => $taxonomy->_builtin,
            ];
        }

        return Response::json([
            'count' => count($result),
            'taxonomies' => $result,
        ]);
    }
}
