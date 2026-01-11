<p align="center">
  <img src="icons/substrate-logo.png" alt="Substrate" width="270">
</p>

<p align="center">
  MCP server for WordPress + Bedrock + Acorn + Sage.<br>
  Gives AI assistants deep context about your Roots stack.
</p>

## Quick Start

```bash
composer require digitalnodecom/substrate --dev
wp acorn substrate:install
```

The install wizard configures MCP for your IDE and generates project guidelines.

## Requirements

- PHP 8.2+
- WordPress 6.0+
- Acorn 5.0+

## Usage

```bash
# Start MCP server (usually handled by your IDE)
wp acorn substrate:mcp

# Refresh caches after plugin/theme changes
wp acorn substrate:update
```

### MCP Configuration

The installer creates `.mcp.json` automatically. Manual setup:

```json
{
  "mcpServers": {
    "substrate": {
      "command": "php",
      "args": ["vendor/bin/acorn", "substrate:mcp"]
    }
  }
}
```

## Tools

**31 tools** across three categories:

### Core (10)

| Tool | Description |
|------|-------------|
| `ApplicationInfo` | PHP, WordPress, Acorn versions, theme info, plugins, packages |
| `BrowserLogs` | Browser console logs captured by Substrate |
| `DatabaseConnections` | Configured database connections |
| `DatabaseQuery` | Execute read-only SQL queries with table prefix support |
| `DatabaseSchema` | Database schema with WordPress core vs custom table detection |
| `GetConfig` | Config values from Acorn config, WP options, env vars, constants |
| `LastError` | Last error/exception from application logs |
| `ListEnvVars` | Environment variable names from .env files |
| `ReadLogEntries` | Log entries from debug.log or Acorn logs |
| `SearchDocs` | Search WordPress, Roots, Tailwind documentation |

### WordPress (16)

| Tool | Description |
|------|-------------|
| `AcfFields` | ACF field groups and fields *(requires ACF)* |
| `GetOption` | WordPress options by name or pattern |
| `ListBlocks` | Registered Gutenberg blocks (core and custom) |
| `ListCronJobs` | Scheduled WP-Cron jobs with next run times |
| `ListHooks` | Active action/filter hooks with callbacks |
| `ListMenus` | Navigation menus and menu locations |
| `ListPlugins` | Installed plugins (active, inactive, must-use) |
| `ListPostTypes` | Registered post types with configuration |
| `ListRestRoutes` | REST API endpoints with methods and arguments |
| `ListShortcodes` | Registered shortcodes with callback info |
| `ListTaxonomies` | Registered taxonomies |
| `ListThemes` | Installed themes with parent/child relationships |
| `ListTransients` | Database transients with expiration times |
| `ListUsers` | User roles and capabilities |
| `ListWidgets` | Widgets and sidebars |
| `WooCommerceInfo` | Store info, settings, gateways *(requires WooCommerce)* |

### Acorn/Sage (5)

| Tool | Description |
|------|-------------|
| `ListAssets` | Assets from Vite manifest |
| `ListBladeComponents` | Blade components (anonymous and class-based) |
| `ListServiceProviders` | Acorn service providers |
| `ListViewComposers` | View composers with their target views |
| `SageInfo` | Sage theme structure, menus, sidebars, build config |

## Configuration

```bash
wp acorn vendor:publish --tag=substrate-config
```

```php
// config/substrate.php
return [
    'enabled' => env('SUBSTRATE_ENABLED', true),
    'browser_logs_watcher' => env('SUBSTRATE_BROWSER_LOGS_WATCHER', true),
    'mcp' => [
        'tools' => [
            'include' => [],
            'exclude' => [],
        ],
    ],
];
```

## Browser Console Capture

Substrate captures browser `console.log`, `console.error`, etc. for the `BrowserLogs` tool.

Auto-injected via middleware, or add manually:

```blade
@substrateJs
</body>
```

## Development

```bash
composer install
composer test      # Run tests
composer lint      # Pint + PHPStan
```

## License

MIT
