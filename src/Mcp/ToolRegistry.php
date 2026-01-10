<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp;

use DirectoryIterator;

class ToolRegistry
{
    /** @var array<int, class-string>|null */
    private static ?array $cachedTools = null;

    /**
     * Get all available tools based on the discovery logic.
     *
     * @return array<int, class-string>
     */
    public static function getAvailableTools(): array
    {
        if (self::$cachedTools !== null) {
            return self::$cachedTools;
        }

        $tools = [];
        $excludedTools = config('substrate.mcp.tools.exclude', []);

        // Discover tools from the Core directory
        $coreToolDir = __DIR__.DIRECTORY_SEPARATOR.'Tools'.DIRECTORY_SEPARATOR.'Core';
        if (is_dir($coreToolDir)) {
            $tools = array_merge($tools, self::discoverToolsInDirectory($coreToolDir, 'Roots\\Substrate\\Mcp\\Tools\\Core\\', $excludedTools));
        }

        // Discover tools from the WordPress directory
        $wpToolDir = __DIR__.DIRECTORY_SEPARATOR.'Tools'.DIRECTORY_SEPARATOR.'WordPress';
        if (is_dir($wpToolDir)) {
            $tools = array_merge($tools, self::discoverToolsInDirectory($wpToolDir, 'Roots\\Substrate\\Mcp\\Tools\\WordPress\\', $excludedTools));
        }

        // Discover tools from the Acorn directory
        $acornToolDir = __DIR__.DIRECTORY_SEPARATOR.'Tools'.DIRECTORY_SEPARATOR.'Acorn';
        if (is_dir($acornToolDir)) {
            $tools = array_merge($tools, self::discoverToolsInDirectory($acornToolDir, 'Roots\\Substrate\\Mcp\\Tools\\Acorn\\', $excludedTools));
        }

        // Add extra tools from configuration
        $extraTools = config('substrate.mcp.tools.include', []);
        foreach ($extraTools as $toolClass) {
            if (class_exists($toolClass) && ! in_array($toolClass, $tools, true)) {
                $tools[] = $toolClass;
            }
        }

        self::$cachedTools = $tools;

        return $tools;
    }

    /**
     * Discover tools in a directory.
     *
     * @param  array<string>  $excludedTools
     * @return array<class-string>
     */
    private static function discoverToolsInDirectory(string $directory, string $namespace, array $excludedTools): array
    {
        $tools = [];

        $toolDir = new DirectoryIterator($directory);

        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqdn = $namespace.$toolFile->getBasename('.php');
                if (class_exists($fqdn) && ! in_array($fqdn, $excludedTools, true)) {
                    $tools[] = $fqdn;
                }
            }
        }

        return $tools;
    }

    /**
     * Check if a tool class is allowed to be executed.
     */
    public static function isToolAllowed(string $toolClass): bool
    {
        return in_array($toolClass, self::getAvailableTools(), true);
    }

    /**
     * Clear the cached tools.
     */
    public static function clearCache(): void
    {
        self::$cachedTools = null;
    }

    /**
     * Get tool names mapped to their full class names.
     *
     * @return array<string, class-string>
     */
    public static function getToolNames(): array
    {
        $tools = self::getAvailableTools();
        $names = [];

        foreach ($tools as $toolClass) {
            $name = class_basename($toolClass);
            $names[$name] = $toolClass;
        }

        return $names;
    }
}
