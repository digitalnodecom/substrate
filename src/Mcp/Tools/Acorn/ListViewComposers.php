<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Acorn;

use Illuminate\Support\Facades\View;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;
use ReflectionMethod;

#[IsReadOnly]
class ListViewComposers extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered Blade view composers in the Sage theme, including which views they serve and what data they provide.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Try to find composers in the theme's app/View/Composers directory
        $composerPath = get_stylesheet_directory().'/app/View/Composers';

        if (! is_dir($composerPath)) {
            return Response::json([
                'message' => 'No View/Composers directory found in theme.',
                'composers' => [],
            ]);
        }

        $composers = [];
        $files = glob($composerPath.'/*.php');

        foreach ($files as $file) {
            $className = 'App\\View\\Composers\\'.basename($file, '.php');

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            // Check if it extends Composer
            if (! $reflection->hasProperty('views')) {
                continue;
            }

            // Get views property
            $viewsProperty = $reflection->getProperty('views');
            $viewsProperty->setAccessible(true);

            // Need an instance to get static property value
            $views = $viewsProperty->isStatic()
                ? $viewsProperty->getValue()
                : ($reflection->getDefaultProperties()['views'] ?? []);

            // Get public methods that might provide data
            $provides = [];
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Skip magic methods and inherited methods
                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }
                // Skip common methods
                if (in_array($method->getName(), ['compose', 'with', 'override', 'register', 'boot'])) {
                    continue;
                }

                $provides[] = $method->getName();
            }

            $composers[$className] = [
                'class' => $className,
                'file' => str_replace(get_stylesheet_directory().'/', '', $file),
                'views' => $views,
                'provides' => $provides,
            ];
        }

        return Response::json([
            'count' => count($composers),
            'composers' => $composers,
        ]);
    }
}
