<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Roots\Substrate\Concerns\MakesHttpRequests;
use Roots\Substrate\SubstrateManager;
use Throwable;

class SearchDocs extends Tool
{
    use MakesHttpRequests;

    public function __construct(protected SubstrateManager $manager)
    {
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Search documentation for WordPress, Roots (Acorn, Sage, Bedrock), and related packages. Use this tool to find up-to-date documentation before implementing features.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query')
                ->required(),
            'source' => $schema->string()
                ->description('Documentation source: "wordpress", "roots", "acorn", "sage", "bedrock", "tailwind", or "all" (default)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $query = trim((string) $request->get('query'));
        $source = $request->get('source', 'all');

        if (empty($query)) {
            return Response::error('Please provide a search query.');
        }

        $results = [];

        // Get documentation URLs based on source
        $sources = $this->getDocSources($source);

        foreach ($sources as $name => $baseUrl) {
            try {
                $searchResults = $this->searchDocumentation($name, $baseUrl, $query);
                if (! empty($searchResults)) {
                    $results[$name] = $searchResults;
                }
            } catch (Throwable $e) {
                $results[$name] = ['error' => $e->getMessage()];
            }
        }

        if (empty($results)) {
            return Response::text("No documentation found for: {$query}");
        }

        return Response::json([
            'query' => $query,
            'results' => $results,
            'suggestions' => $this->getSuggestions($query),
        ]);
    }

    /**
     * Get documentation sources based on filter.
     *
     * @return array<string, string>
     */
    protected function getDocSources(string $source): array
    {
        $allSources = [
            'wordpress' => 'https://developer.wordpress.org',
            'roots' => 'https://roots.io/docs',
            'acorn' => 'https://roots.io/acorn/docs',
            'sage' => 'https://roots.io/sage/docs',
            'bedrock' => 'https://roots.io/bedrock/docs',
            'tailwind' => 'https://tailwindcss.com/docs',
        ];

        if ($source === 'all') {
            return $allSources;
        }

        if (isset($allSources[$source])) {
            return [$source => $allSources[$source]];
        }

        return $allSources;
    }

    /**
     * Search documentation for a specific source.
     *
     * @return array<string, mixed>
     */
    protected function searchDocumentation(string $name, string $baseUrl, string $query): array
    {
        // Build documentation links based on the query
        $links = $this->buildDocLinks($name, $baseUrl, $query);

        return [
            'source' => $name,
            'base_url' => $baseUrl,
            'suggested_links' => $links,
        ];
    }

    /**
     * Build relevant documentation links.
     *
     * @return array<string, string>
     */
    protected function buildDocLinks(string $source, string $baseUrl, string $query): array
    {
        $querySlug = strtolower(str_replace([' ', '_'], '-', $query));
        $links = [];

        switch ($source) {
            case 'wordpress':
                $links = [
                    'Reference' => "{$baseUrl}/reference/functions/{$querySlug}/",
                    'Hooks' => "{$baseUrl}/reference/hooks/{$querySlug}/",
                    'Classes' => "{$baseUrl}/reference/classes/{$querySlug}/",
                    'REST API' => "{$baseUrl}/rest-api/reference/",
                    'Block Editor' => "{$baseUrl}/block-editor/",
                    'Search' => "{$baseUrl}/?s={$query}",
                ];
                break;

            case 'roots':
            case 'acorn':
            case 'sage':
            case 'bedrock':
                $links = [
                    'Documentation' => "{$baseUrl}/{$querySlug}/",
                    'Getting Started' => "{$baseUrl}/getting-started/",
                    'Configuration' => "{$baseUrl}/configuration/",
                ];
                break;

            case 'tailwind':
                $links = [
                    'Utility Classes' => "{$baseUrl}/{$querySlug}",
                    'Configuration' => "{$baseUrl}/configuration",
                    'Search' => "https://tailwindcss.com/docs?q={$query}",
                ];
                break;
        }

        return $links;
    }

    /**
     * Get search suggestions based on query.
     *
     * @return array<string>
     */
    protected function getSuggestions(string $query): array
    {
        $suggestions = [];
        $queryLower = strtolower($query);

        // WordPress-specific suggestions
        $wpKeywords = [
            'hook' => 'Try searching for specific hook names like "init", "wp_head", "the_content"',
            'post' => 'For post-related queries, check WP_Query, get_posts(), or the Posts REST API',
            'user' => 'For user queries, see WP_User, wp_get_current_user(), or Users REST API',
            'meta' => 'For metadata, see get_post_meta(), update_post_meta(), or meta queries',
            'block' => 'For Gutenberg blocks, check the Block Editor Handbook',
            'rest' => 'For REST API, see /wp-json/wp/v2/ endpoints documentation',
        ];

        foreach ($wpKeywords as $keyword => $suggestion) {
            if (str_contains($queryLower, $keyword)) {
                $suggestions[] = $suggestion;
            }
        }

        // Roots-specific suggestions
        $rootsKeywords = [
            'blade' => 'For Blade templates in Sage, check the Sage documentation on views',
            'composer' => 'For View Composers, see Sage documentation on data/view composers',
            'provider' => 'For Service Providers, check Acorn documentation',
            'vite' => 'For asset compilation, see Sage documentation on Vite/Bud',
            'tailwind' => 'For Tailwind CSS, the theme.json is auto-generated from your config',
        ];

        foreach ($rootsKeywords as $keyword => $suggestion) {
            if (str_contains($queryLower, $keyword)) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }
}
