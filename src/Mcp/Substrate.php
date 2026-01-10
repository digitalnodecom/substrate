<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Roots\Substrate\Mcp\Methods\CallToolWithExecutor;
// Core tools
use Roots\Substrate\Mcp\Tools\Core\ApplicationInfo;
use Roots\Substrate\Mcp\Tools\Core\DatabaseConnections;
use Roots\Substrate\Mcp\Tools\Core\DatabaseQuery;
use Roots\Substrate\Mcp\Tools\Core\DatabaseSchema;
use Roots\Substrate\Mcp\Tools\Core\GetConfig;
use Roots\Substrate\Mcp\Tools\Core\LastError;
use Roots\Substrate\Mcp\Tools\Core\ListEnvVars;
use Roots\Substrate\Mcp\Tools\Core\ReadLogEntries;
use Roots\Substrate\Mcp\Tools\Core\BrowserLogs;
use Roots\Substrate\Mcp\Tools\Core\SearchDocs;
// WordPress tools
use Roots\Substrate\Mcp\Tools\WordPress\GetOption;
use Roots\Substrate\Mcp\Tools\WordPress\ListBlocks;
use Roots\Substrate\Mcp\Tools\WordPress\ListCronJobs;
use Roots\Substrate\Mcp\Tools\WordPress\ListHooks;
use Roots\Substrate\Mcp\Tools\WordPress\ListMenus;
use Roots\Substrate\Mcp\Tools\WordPress\ListPlugins;
use Roots\Substrate\Mcp\Tools\WordPress\ListPostTypes;
use Roots\Substrate\Mcp\Tools\WordPress\ListRestRoutes;
use Roots\Substrate\Mcp\Tools\WordPress\ListShortcodes;
use Roots\Substrate\Mcp\Tools\WordPress\ListTaxonomies;
use Roots\Substrate\Mcp\Tools\WordPress\ListThemes;
use Roots\Substrate\Mcp\Tools\WordPress\ListTransients;
use Roots\Substrate\Mcp\Tools\WordPress\ListUsers;
use Roots\Substrate\Mcp\Tools\WordPress\ListWidgets;
use Roots\Substrate\Mcp\Tools\WordPress\AcfFields;
use Roots\Substrate\Mcp\Tools\WordPress\WooCommerceInfo;
// Acorn tools
use Roots\Substrate\Mcp\Tools\Acorn\ListAssets;
use Roots\Substrate\Mcp\Tools\Acorn\ListBladeComponents;
use Roots\Substrate\Mcp\Tools\Acorn\ListServiceProviders;
use Roots\Substrate\Mcp\Tools\Acorn\ListViewComposers;
use Roots\Substrate\Mcp\Tools\Acorn\SageInfo;

class Substrate extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Substrate';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.1.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'WordPress/Acorn/Sage ecosystem MCP server offering database schema access, WP-CLI commands, error logs, code execution, and semantic documentation search. Substrate helps with WordPress theme and plugin development using the Roots stack.';

    /**
     * The default pagination length for resources that support pagination.
     */
    public int $defaultPaginationLength = 50;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];

    /**
     * Boot the MCP server.
     */
    protected function boot(): void
    {
        $this->tools = $this->discoverTools();
        $this->resources = $this->discoverResources();
        $this->prompts = $this->discoverPrompts();

        // Override the tools/call method to use our ToolExecutor
        $this->methods['tools/call'] = CallToolWithExecutor::class;
    }

    /**
     * Discover available tools.
     *
     * @return array<int, class-string<Tool>>
     */
    protected function discoverTools(): array
    {
        return $this->filterPrimitives([
            // Core tools
            ApplicationInfo::class,
            DatabaseConnections::class,
            DatabaseQuery::class,
            DatabaseSchema::class,
            GetConfig::class,
            LastError::class,
            ListEnvVars::class,
            ReadLogEntries::class,
            BrowserLogs::class,
            SearchDocs::class,

            // WordPress tools
            GetOption::class,
            ListBlocks::class,
            ListCronJobs::class,
            ListHooks::class,
            ListMenus::class,
            ListPlugins::class,
            ListPostTypes::class,
            ListRestRoutes::class,
            ListShortcodes::class,
            ListTaxonomies::class,
            ListThemes::class,
            ListTransients::class,
            ListUsers::class,
            ListWidgets::class,
            AcfFields::class,
            WooCommerceInfo::class,

            // Acorn/Sage tools
            ListAssets::class,
            ListBladeComponents::class,
            ListServiceProviders::class,
            ListViewComposers::class,
            SageInfo::class,
        ], 'tools');
    }

    /**
     * Discover available resources.
     *
     * @return array<int, class-string<Resource>>
     */
    protected function discoverResources(): array
    {
        $availableResources = [
            Resources\ApplicationInfo::class,
        ];

        return $this->filterPrimitives($availableResources, 'resources');
    }

    /**
     * Discover available prompts.
     *
     * @return array<int, class-string<Prompt>>
     */
    protected function discoverPrompts(): array
    {
        return $this->filterPrimitives([], 'prompts');
    }

    /**
     * Filter primitives based on configuration.
     *
     * @param  array<int, Tool|Resource|Prompt|class-string>  $availablePrimitives
     * @return array<int, Tool|Resource|Prompt|class-string>
     */
    private function filterPrimitives(array $availablePrimitives, string $type): array
    {
        $excludeList = config("substrate.mcp.{$type}.exclude", []);
        $includeList = config("substrate.mcp.{$type}.include", []);

        $filtered = collect($availablePrimitives)->reject(function (string|object $item) use ($excludeList): bool {
            $className = is_string($item) ? $item : $item::class;

            return in_array($className, $excludeList, true);
        });

        $explicitlyIncluded = collect($includeList)
            ->filter(fn (string $class): bool => class_exists($class));

        return $filtered
            ->merge($explicitlyIncluded)
            ->values()
            ->all();
    }
}
