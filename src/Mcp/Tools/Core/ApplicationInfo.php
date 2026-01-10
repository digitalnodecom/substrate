<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Roots\Substrate\SubstrateManager;

#[IsReadOnly]
class ApplicationInfo extends Tool
{
    public function __construct(protected SubstrateManager $manager)
    {
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Get comprehensive application information including PHP version, WordPress version, Acorn version, active theme, plugins, and installed Composer packages. Use this tool at the start of each chat to understand the project context.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        return Response::json([
            'php_version' => PHP_VERSION,
            'wordpress' => $this->getWordPressInfo(),
            'acorn' => $this->manager->getAcornInfo(),
            'theme' => $this->manager->getThemeInfo(),
            'plugins' => $this->getPluginsInfo(),
            'packages' => $this->manager->getInstalledPackages(),
        ]);
    }

    /**
     * Get WordPress information.
     *
     * @return array<string, mixed>
     */
    protected function getWordPressInfo(): array
    {
        global $wp_version;

        $info = [
            'version' => $wp_version ?? 'unknown',
            'environment' => function_exists('wp_get_environment_type')
                ? wp_get_environment_type()
                : 'unknown',
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'multisite' => function_exists('is_multisite') && is_multisite(),
        ];

        if (function_exists('site_url')) {
            $info['site_url'] = site_url();
            $info['home_url'] = home_url();
        }

        if (function_exists('get_locale')) {
            $info['locale'] = get_locale();
        }

        return $info;
    }

    /**
     * Get active plugins information.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getPluginsInfo(): array
    {
        if (! function_exists('get_plugins') || ! function_exists('is_plugin_active')) {
            return [];
        }

        // Ensure the plugin functions are available
        if (! function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = [];
        $inactivePlugins = [];

        foreach ($allPlugins as $pluginFile => $pluginData) {
            $pluginInfo = [
                'name' => $pluginData['Name'] ?? '',
                'version' => $pluginData['Version'] ?? '',
                'author' => $pluginData['Author'] ?? '',
            ];

            if (is_plugin_active($pluginFile)) {
                $activePlugins[$pluginFile] = $pluginInfo;
            } else {
                $inactivePlugins[$pluginFile] = $pluginInfo;
            }
        }

        return [
            'active' => $activePlugins,
            'inactive' => $inactivePlugins,
        ];
    }
}
