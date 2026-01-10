<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListThemes extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all installed WordPress themes with their status and metadata, including parent/child theme relationships.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('wp_get_themes')) {
            return Response::error('WordPress theme functions not available.');
        }

        $themes = wp_get_themes();
        $activeTheme = wp_get_theme();
        $result = [];

        foreach ($themes as $stylesheet => $theme) {
            $isActive = $stylesheet === $activeTheme->get_stylesheet();
            $parentTheme = $theme->parent();

            $result[$stylesheet] = [
                'stylesheet' => $stylesheet,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'author_uri' => $theme->get('AuthorURI'),
                'description' => $theme->get('Description'),
                'theme_uri' => $theme->get('ThemeURI'),
                'text_domain' => $theme->get('TextDomain'),
                'requires_wp' => $theme->get('RequiresWP'),
                'requires_php' => $theme->get('RequiresPHP'),
                'is_active' => $isActive,
                'is_child_theme' => $parentTheme !== false,
                'parent_theme' => $parentTheme ? $parentTheme->get_stylesheet() : null,
                'template' => $theme->get_template(),
                'path' => $theme->get_stylesheet_directory(),
                'tags' => $theme->get('Tags') ?: [],
            ];
        }

        return Response::json([
            'active_theme' => $activeTheme->get_stylesheet(),
            'count' => count($result),
            'themes' => $result,
        ]);
    }
}
