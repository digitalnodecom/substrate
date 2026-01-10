<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class DatabaseQuery extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Execute a read-only SQL query against the WordPress database. Use {prefix} as a placeholder for the table prefix (e.g., "SELECT * FROM {prefix}posts").';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The SQL query to execute. Only read-only queries are allowed (SELECT, SHOW, EXPLAIN, DESCRIBE). Use {prefix} for table prefix.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wpdb;

        $query = trim((string) $request->get('query'));

        // Replace {prefix} placeholder with actual prefix
        $query = str_replace('{prefix}', $wpdb->prefix, $query);

        // Validate read-only query
        $token = strtok(ltrim($query), " \t\n\r");
        if (! $token) {
            return Response::error('Please provide a valid query.');
        }

        $firstWord = strtoupper($token);

        $allowList = [
            'SELECT',
            'SHOW',
            'EXPLAIN',
            'DESCRIBE',
            'DESC',
            'WITH',
        ];

        $isReadOnly = in_array($firstWord, $allowList, true);

        // Additional validation for WITH ... SELECT
        if ($firstWord === 'WITH' && ! preg_match('/with\s+.*select\b/i', $query)) {
            $isReadOnly = false;
        }

        if (! $isReadOnly) {
            return Response::error('Only read-only queries are allowed (SELECT, SHOW, EXPLAIN, DESCRIBE).');
        }

        try {
            $results = $wpdb->get_results($query, ARRAY_A);

            if ($wpdb->last_error) {
                return Response::error('Query failed: '.$wpdb->last_error);
            }

            return Response::json([
                'results' => $results,
                'num_rows' => count($results),
                'query' => $query,
            ]);
        } catch (Throwable $throwable) {
            return Response::error('Query failed: '.$throwable->getMessage());
        }
    }
}
