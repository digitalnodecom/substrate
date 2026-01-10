<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Substrate Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Substrate functionality - which
    | will prevent Substrate's routes from being registered and will also
    | disable Substrate's browser logging functionality from operating.
    |
    */

    'enabled' => env('SUBSTRATE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | The following option may be used to enable or disable the browser logs
    | watcher feature within Substrate. The log watcher will read any
    | errors within the browser's console to give Substrate better context.
    |
    */

    'browser_logs_watcher' => env('SUBSTRATE_BROWSER_LOGS_WATCHER', true),

    /*
    |--------------------------------------------------------------------------
    | MCP Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which tools, resources, and prompts are available in the
    | MCP server. You can exclude specific tools or include additional ones.
    |
    */

    'mcp' => [
        'tools' => [
            'include' => [],
            'exclude' => [],
        ],
        'resources' => [
            'include' => [],
            'exclude' => [],
        ],
        'prompts' => [
            'include' => [],
            'exclude' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Search
    |--------------------------------------------------------------------------
    |
    | Configure the documentation endpoints for semantic search.
    |
    */

    'docs' => [
        'wordpress' => 'https://developer.wordpress.org/',
        'roots' => 'https://roots.io/docs/',
        'acorn' => 'https://roots.io/acorn/docs/',
        'sage' => 'https://roots.io/sage/docs/',
        'bedrock' => 'https://roots.io/bedrock/docs/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guidelines Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the AI guidelines generation behavior.
    |
    */

    'guidelines' => [
        'enforce_tests' => false,
        'localization' => true,
        'api' => true,
    ],

];
