<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Acorn;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListBladeComponents extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all available Blade components in the Sage theme, including anonymous components from the views/components directory.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $themePath = get_stylesheet_directory();
        $componentsPath = $themePath.'/resources/views/components';

        $components = [];

        // Scan for anonymous Blade components
        if (is_dir($componentsPath)) {
            $components = array_merge($components, $this->scanComponentsDirectory($componentsPath, ''));
        }

        // Check for class-based components in app/View/Components
        $classComponentsPath = $themePath.'/app/View/Components';
        if (is_dir($classComponentsPath)) {
            $components = array_merge($components, $this->scanClassComponents($classComponentsPath));
        }

        return Response::json([
            'count' => count($components),
            'components' => $components,
        ]);
    }

    /**
     * Scan directory for anonymous Blade components.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function scanComponentsDirectory(string $directory, string $prefix): array
    {
        $components = [];
        $items = glob($directory.'/*');

        foreach ($items as $item) {
            $basename = basename($item);

            if (is_dir($item)) {
                // Recurse into subdirectory
                $subPrefix = $prefix ? $prefix.'.'.$basename : $basename;
                $components = array_merge($components, $this->scanComponentsDirectory($item, $subPrefix));
            } elseif (str_ends_with($basename, '.blade.php')) {
                $componentName = str_replace('.blade.php', '', $basename);
                $fullName = $prefix ? $prefix.'.'.$componentName : $componentName;

                // Parse the component file for @props
                $props = $this->extractProps($item);

                $components[$fullName] = [
                    'name' => $fullName,
                    'tag' => '<x-'.$fullName.' />',
                    'type' => 'anonymous',
                    'file' => str_replace(get_stylesheet_directory().'/', '', $item),
                    'props' => $props,
                ];
            }
        }

        return $components;
    }

    /**
     * Scan for class-based components.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function scanClassComponents(string $directory): array
    {
        $components = [];
        $files = glob($directory.'/*.php');

        foreach ($files as $file) {
            $className = 'App\\View\\Components\\'.basename($file, '.php');

            if (! class_exists($className)) {
                continue;
            }

            $componentName = $this->classToComponentName(basename($file, '.php'));

            $components[$componentName] = [
                'name' => $componentName,
                'tag' => '<x-'.$componentName.' />',
                'type' => 'class',
                'class' => $className,
                'file' => str_replace(get_stylesheet_directory().'/', '', $file),
                'props' => $this->extractClassProps($className),
            ];
        }

        return $components;
    }

    /**
     * Convert class name to component name.
     */
    protected function classToComponentName(string $className): string
    {
        // Convert PascalCase to kebab-case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
    }

    /**
     * Extract @props from an anonymous component file.
     *
     * @return array<string>
     */
    protected function extractProps(string $filePath): array
    {
        $content = file_get_contents($filePath);

        if (preg_match('/@props\s*\(\s*\[(.*?)\]\s*\)/s', $content, $matches)) {
            // Parse the props array
            preg_match_all("/['\"]([^'\"]+)['\"]/", $matches[1], $propMatches);

            return $propMatches[1] ?? [];
        }

        return [];
    }

    /**
     * Extract props from a class-based component.
     *
     * @return array<string>
     */
    protected function extractClassProps(string $className): array
    {
        try {
            $reflection = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if (! $constructor) {
                return [];
            }

            $props = [];
            foreach ($constructor->getParameters() as $param) {
                $props[] = $param->getName();
            }

            return $props;
        } catch (\Throwable) {
            return [];
        }
    }
}
