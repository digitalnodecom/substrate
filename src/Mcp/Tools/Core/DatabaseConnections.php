<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\Core;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class DatabaseConnections extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List the configured database connections for this WordPress/Acorn application, including the WordPress database configuration.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wpdb;

        $connections = [
            'wordpress' => [
                'name' => 'wordpress',
                'driver' => 'mysql',
                'host' => defined('DB_HOST') ? DB_HOST : 'unknown',
                'database' => defined('DB_NAME') ? DB_NAME : 'unknown',
                'prefix' => $wpdb->prefix ?? 'wp_',
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'collation' => defined('DB_COLLATE') ? DB_COLLATE : 'utf8mb4_unicode_ci',
            ],
        ];

        // Add Acorn/Laravel database connections if configured
        $laravelConnections = config('database.connections', []);
        foreach ($laravelConnections as $name => $config) {
            if (! isset($connections[$name])) {
                $connections[$name] = [
                    'name' => $name,
                    'driver' => $config['driver'] ?? 'unknown',
                    'host' => $config['host'] ?? 'unknown',
                    'database' => $config['database'] ?? 'unknown',
                ];
            }
        }

        return Response::json([
            'default_connection' => 'wordpress',
            'connections' => $connections,
        ]);
    }
}
