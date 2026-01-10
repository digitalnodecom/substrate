<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WP_Block_Type_Registry;

#[IsReadOnly]
class ListBlocks extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered Gutenberg blocks including core blocks, plugin blocks, and custom theme blocks.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by type: "core", "custom", or "all" (default)'),
            'category' => $schema->string()
                ->description('Filter by block category (e.g., "text", "media", "design", "widgets", "embed")'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! class_exists('WP_Block_Type_Registry')) {
            return Response::error('WordPress block editor not available.');
        }

        $filter = $request->get('type', 'all');
        $categoryFilter = $request->get('category');

        $registry = WP_Block_Type_Registry::get_instance();
        $blocks = $registry->get_all_registered();
        $result = [];

        foreach ($blocks as $blockName => $block) {
            $isCore = str_starts_with($blockName, 'core/');

            // Apply type filter
            if ($filter === 'core' && ! $isCore) {
                continue;
            }
            if ($filter === 'custom' && $isCore) {
                continue;
            }

            // Apply category filter
            if ($categoryFilter && ($block->category ?? '') !== $categoryFilter) {
                continue;
            }

            $result[$blockName] = [
                'name' => $blockName,
                'title' => $block->title ?? $blockName,
                'description' => $block->description ?? '',
                'category' => $block->category ?? 'common',
                'icon' => is_string($block->icon) ? $block->icon : 'block-default',
                'keywords' => $block->keywords ?? [],
                'supports' => $block->supports ?? [],
                'attributes' => $this->formatAttributes($block->attributes ?? []),
                'is_core' => $isCore,
                'is_dynamic' => $block->is_dynamic(),
                'parent' => $block->parent ?? null,
                'ancestor' => $block->ancestor ?? null,
                'provides_context' => $block->provides_context ?? [],
                'uses_context' => $block->uses_context ?? [],
            ];
        }

        // Get block categories
        $categories = [];
        if (function_exists('get_block_categories')) {
            // Need a post object for get_block_categories
            $categories = function_exists('get_default_block_categories')
                ? get_default_block_categories()
                : [];
        }

        return Response::json([
            'count' => count($result),
            'blocks' => $result,
            'categories' => $categories,
        ]);
    }

    /**
     * Format block attributes for output.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function formatAttributes(array $attributes): array
    {
        $formatted = [];

        foreach ($attributes as $name => $config) {
            $formatted[$name] = [
                'type' => $config['type'] ?? 'string',
                'default' => $config['default'] ?? null,
            ];
        }

        return $formatted;
    }
}
