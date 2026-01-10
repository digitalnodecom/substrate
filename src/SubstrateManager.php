<?php

declare(strict_types=1);

namespace Roots\Substrate;

/**
 * Manages Substrate configuration and code environments.
 */
class SubstrateManager
{
    /**
     * The Substrate version.
     */
    public const VERSION = '0.1.0';

    /**
     * Get the Substrate version.
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * Check if Substrate is enabled.
     */
    public function enabled(): bool
    {
        return (bool) config('substrate.enabled', true);
    }

    /**
     * Get the WordPress installation info.
     *
     * @return array<string, mixed>
     */
    public function getWordPressInfo(): array
    {
        global $wp_version;

        return [
            'version' => $wp_version ?? 'unknown',
            'environment' => function_exists('wp_get_environment_type')
                ? wp_get_environment_type()
                : 'unknown',
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'multisite' => function_exists('is_multisite') && is_multisite(),
        ];
    }

    /**
     * Get the Acorn installation info.
     *
     * @return array<string, mixed>
     */
    public function getAcornInfo(): array
    {
        return [
            'version' => defined('Roots\\Acorn\\Application::VERSION')
                ? \Roots\Acorn\Application::VERSION
                : $this->getPackageVersion('roots/acorn'),
        ];
    }

    /**
     * Get the active theme info.
     *
     * @return array<string, mixed>
     */
    public function getThemeInfo(): array
    {
        if (! function_exists('wp_get_theme')) {
            return [];
        }

        $theme = wp_get_theme();

        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
            'path' => $theme->get_stylesheet_directory(),
            'uri' => $theme->get_stylesheet_directory_uri(),
            'parent' => $theme->parent() ? $theme->parent()->get('Name') : null,
            'text_domain' => $theme->get('TextDomain'),
            'requires_php' => $theme->get('RequiresPHP'),
            'requires_wp' => $theme->get('RequiresWP'),
        ];
    }

    /**
     * Get a package version from composer.lock.
     */
    protected function getPackageVersion(string $package): string
    {
        $lockFile = base_path('composer.lock');

        if (! file_exists($lockFile)) {
            return 'unknown';
        }

        $lock = json_decode(file_get_contents($lockFile), true);

        if (! $lock || ! isset($lock['packages'])) {
            return 'unknown';
        }

        foreach ($lock['packages'] as $pkg) {
            if ($pkg['name'] === $package) {
                return $pkg['version'] ?? 'unknown';
            }
        }

        return 'unknown';
    }

    /**
     * Get all installed Composer packages.
     *
     * @return array<string, string>
     */
    public function getInstalledPackages(): array
    {
        $lockFile = base_path('composer.lock');

        if (! file_exists($lockFile)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockFile), true);

        if (! $lock) {
            return [];
        }

        $packages = [];

        foreach ($lock['packages'] ?? [] as $pkg) {
            $packages[$pkg['name']] = $pkg['version'] ?? 'unknown';
        }

        return $packages;
    }
}
