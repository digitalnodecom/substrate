<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Acorn;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListServiceProviders extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered Acorn service providers, including theme providers and package providers.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $app = app();

        // Get loaded providers
        $loadedProviders = $app->getLoadedProviders();
        $result = [];

        foreach ($loadedProviders as $providerClass => $loaded) {
            $isThemeProvider = str_starts_with($providerClass, 'App\\');
            $isAcornProvider = str_starts_with($providerClass, 'Roots\\Acorn\\');
            $isLaravelProvider = str_starts_with($providerClass, 'Illuminate\\');

            $category = match (true) {
                $isThemeProvider => 'theme',
                $isAcornProvider => 'acorn',
                $isLaravelProvider => 'framework',
                default => 'package',
            };

            // Try to get the file path for theme providers
            $filePath = null;
            if ($isThemeProvider && class_exists($providerClass)) {
                try {
                    $reflection = new \ReflectionClass($providerClass);
                    $fullPath = $reflection->getFileName();
                    if ($fullPath && function_exists('get_stylesheet_directory')) {
                        $filePath = str_replace(get_stylesheet_directory().'/', '', $fullPath);
                    }
                } catch (\Throwable) {
                    // Ignore reflection errors
                }
            }

            $result[$providerClass] = [
                'class' => $providerClass,
                'category' => $category,
                'loaded' => $loaded,
                'file' => $filePath,
            ];
        }

        // Group by category
        $grouped = [
            'theme' => [],
            'acorn' => [],
            'framework' => [],
            'package' => [],
        ];

        foreach ($result as $provider) {
            $grouped[$provider['category']][$provider['class']] = $provider;
        }

        return Response::json([
            'total' => count($result),
            'counts' => [
                'theme' => count($grouped['theme']),
                'acorn' => count($grouped['acorn']),
                'framework' => count($grouped['framework']),
                'package' => count($grouped['package']),
            ],
            'providers' => $grouped,
        ]);
    }
}
