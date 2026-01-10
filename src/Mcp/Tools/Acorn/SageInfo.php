<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Acorn;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SageInfo extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get comprehensive information about the Sage theme structure, including views, composers, components, menus, sidebars, and build configuration.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $theme = wp_get_theme();
        $themePath = get_stylesheet_directory();

        $result = [
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'path' => $themePath,
                'uri' => get_stylesheet_directory_uri(),
                'text_domain' => $theme->get('TextDomain'),
                'requires_php' => $theme->get('RequiresPHP'),
                'requires_wp' => $theme->get('RequiresWP'),
            ],
            'structure' => $this->analyzeStructure($themePath),
            'menus' => $this->getRegisteredMenus(),
            'sidebars' => $this->getRegisteredSidebars(),
            'theme_supports' => $this->getThemeSupports(),
            'build' => $this->getBuildConfig($themePath),
        ];

        return Response::json($result);
    }

    /**
     * Analyze the theme directory structure.
     *
     * @return array<string, mixed>
     */
    protected function analyzeStructure(string $themePath): array
    {
        return [
            'views' => $this->listFiles($themePath.'/resources/views', 'blade.php'),
            'composers' => $this->listFiles($themePath.'/app/View/Composers', 'php'),
            'providers' => $this->listFiles($themePath.'/app/Providers', 'php'),
            'components' => $this->listFiles($themePath.'/resources/views/components', 'blade.php'),
            'js_files' => $this->listFiles($themePath.'/resources/js', 'js'),
            'css_files' => $this->listFiles($themePath.'/resources/css', 'css'),
        ];
    }

    /**
     * List files in a directory with a specific extension.
     *
     * @return array<string>
     */
    protected function listFiles(string $directory, string $extension): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.'.$extension)) {
                $relativePath = str_replace($directory.'/', '', $file->getPathname());
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Get registered navigation menus.
     *
     * @return array<string, string>
     */
    protected function getRegisteredMenus(): array
    {
        return get_registered_nav_menus();
    }

    /**
     * Get registered sidebars.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getRegisteredSidebars(): array
    {
        global $wp_registered_sidebars;

        $sidebars = [];
        foreach ($wp_registered_sidebars ?? [] as $id => $sidebar) {
            $sidebars[$id] = [
                'name' => $sidebar['name'],
                'description' => $sidebar['description'] ?? '',
            ];
        }

        return $sidebars;
    }

    /**
     * Get theme supports.
     *
     * @return array<string, mixed>
     */
    protected function getThemeSupports(): array
    {
        $supports = [];
        $features = [
            'title-tag',
            'post-thumbnails',
            'html5',
            'custom-logo',
            'customize-selective-refresh-widgets',
            'responsive-embeds',
            'editor-styles',
            'wp-block-styles',
            'align-wide',
            'custom-spacing',
            'custom-units',
        ];

        foreach ($features as $feature) {
            $support = get_theme_support($feature);
            $supports[$feature] = $support !== false;
        }

        return $supports;
    }

    /**
     * Get build configuration.
     *
     * @return array<string, mixed>
     */
    protected function getBuildConfig(string $themePath): array
    {
        $config = [
            'tool' => null,
            'config_file' => null,
            'tailwind' => false,
        ];

        // Check for Vite
        if (file_exists($themePath.'/vite.config.js')) {
            $config['tool'] = 'vite';
            $config['config_file'] = 'vite.config.js';
        } elseif (file_exists($themePath.'/vite.config.ts')) {
            $config['tool'] = 'vite';
            $config['config_file'] = 'vite.config.ts';
        }

        // Check for Bud
        if (file_exists($themePath.'/bud.config.js')) {
            $config['tool'] = 'bud';
            $config['config_file'] = 'bud.config.js';
        }

        // Check for Tailwind
        if (file_exists($themePath.'/tailwind.config.js') || file_exists($themePath.'/tailwind.config.ts')) {
            $config['tailwind'] = true;
        }

        // Check package.json for more info
        $packageJson = $themePath.'/package.json';
        if (file_exists($packageJson)) {
            $package = json_decode(file_get_contents($packageJson), true);
            $config['scripts'] = array_keys($package['scripts'] ?? []);
            $config['dependencies'] = array_keys($package['dependencies'] ?? []);
            $config['dev_dependencies'] = array_keys($package['devDependencies'] ?? []);
        }

        return $config;
    }
}
