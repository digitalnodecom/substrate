<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class DatabaseSchema extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Read the WordPress database schema, including table names, columns, data types, indexes, and foreign keys. Identifies WordPress core tables vs custom/plugin tables.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Filter tables by name (e.g., "posts", "woocommerce", "acf")'),
            'type' => $schema->string()
                ->description('Filter by table type: "core" (WordPress core), "custom" (plugins/themes), or "all" (default)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wpdb;

        $filter = $request->get('filter', '');
        $type = $request->get('type', 'all');
        $cacheKey = "substrate:database-schema:{$filter}:{$type}";

        $schema = Cache::remember($cacheKey, 20, fn (): array => $this->getDatabaseStructure($filter, $type));

        return Response::json($schema);
    }

    /**
     * Get the database structure.
     *
     * @return array<string, mixed>
     */
    protected function getDatabaseStructure(string $filter, string $type): array
    {
        global $wpdb;

        return [
            'engine' => $this->getDatabaseEngine(),
            'prefix' => $wpdb->prefix,
            'tables' => $this->getAllTablesStructure($filter, $type),
        ];
    }

    /**
     * Get the database engine/version.
     */
    protected function getDatabaseEngine(): string
    {
        global $wpdb;

        $version = $wpdb->get_var('SELECT VERSION()');

        return $version ? "MySQL {$version}" : 'MySQL';
    }

    /**
     * Get all tables structure.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getAllTablesStructure(string $filter, string $type): array
    {
        global $wpdb;

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $structures = [];

        $coreTables = $this->getWordPressCoreTables();

        foreach ($tables as $table) {
            $tableName = $table[0];

            // Apply filter
            if ($filter && stripos($tableName, $filter) === false) {
                continue;
            }

            // Determine if core or custom
            $isCore = $this->isCoreTable($tableName, $coreTables);

            // Apply type filter
            if ($type === 'core' && ! $isCore) {
                continue;
            }
            if ($type === 'custom' && $isCore) {
                continue;
            }

            $structures[$tableName] = $this->getTableStructure($tableName, $isCore);
        }

        return $structures;
    }

    /**
     * Get WordPress core table names.
     *
     * @return array<string>
     */
    protected function getWordPressCoreTables(): array
    {
        global $wpdb;

        return [
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->terms,
            $wpdb->term_taxonomy,
            $wpdb->term_relationships,
            $wpdb->termmeta,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->options,
            $wpdb->links,
        ];
    }

    /**
     * Check if a table is a WordPress core table.
     *
     * @param  array<string>  $coreTables
     */
    protected function isCoreTable(string $tableName, array $coreTables): bool
    {
        return in_array($tableName, $coreTables, true);
    }

    /**
     * Get structure for a single table.
     *
     * @return array<string, mixed>
     */
    protected function getTableStructure(string $tableName, bool $isCore): array
    {
        global $wpdb;

        $columns = $wpdb->get_results("DESCRIBE `{$tableName}`", ARRAY_A);
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A);

        $columnDetails = [];
        foreach ($columns as $column) {
            $columnDetails[$column['Field']] = [
                'type' => $column['Type'],
                'null' => $column['Null'] === 'YES',
                'key' => $column['Key'],
                'default' => $column['Default'],
                'extra' => $column['Extra'],
            ];
        }

        $indexDetails = [];
        foreach ($indexes as $index) {
            $indexName = $index['Key_name'];
            if (! isset($indexDetails[$indexName])) {
                $indexDetails[$indexName] = [
                    'columns' => [],
                    'unique' => $index['Non_unique'] === '0',
                    'type' => $index['Index_type'],
                ];
            }
            $indexDetails[$indexName]['columns'][] = $index['Column_name'];
        }

        return [
            'is_core' => $isCore,
            'columns' => $columnDetails,
            'indexes' => $indexDetails,
        ];
    }
}
