<?php

declare(strict_types=1);

namespace Roots\Substrate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

class InstallCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'substrate:install
        {--skip-guidelines : Skip generating AI guidelines}
        {--skip-mcp : Skip generating MCP configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Install and configure Substrate for your WordPress/Acorn project';

    /**
     * The project root directory.
     */
    protected string $projectRoot;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->projectRoot = $this->detectProjectRoot();

        $this->displayHeader();
        $this->discoverEnvironment();

        $installGuidelines = ! $this->option('skip-guidelines');
        $installMcp = ! $this->option('skip-mcp');

        if ($installMcp) {
            $this->installMcpConfig();
        }

        if ($installGuidelines) {
            $this->installGuidelines();
        }

        $this->displayOutro();

        return self::SUCCESS;
    }

    /**
     * Detect the project root directory.
     *
     * Looks for Bedrock indicators, falls back to cwd.
     */
    protected function detectProjectRoot(): string
    {
        $cwd = getcwd() ?: base_path();

        // If cwd has wp-cli.yml or bedrock-style composer.json, use it
        if (file_exists($cwd.'/wp-cli.yml') || file_exists($cwd.'/config/application.php')) {
            return $cwd;
        }

        // Walk up from theme directory looking for project root
        $path = base_path();
        while ($path !== dirname($path)) {
            if (file_exists($path.'/wp-cli.yml') || file_exists($path.'/config/application.php')) {
                return $path;
            }
            $path = dirname($path);
        }

        // Fallback to cwd
        return $cwd;
    }

    /**
     * Display the installation header.
     */
    protected function displayHeader(): void
    {
        note($this->getLogo());
        intro('Substrate Installation Wizard');

        $siteName = function_exists('get_bloginfo')
            ? get_bloginfo('name')
            : 'your project';

        note("Setting up Substrate for {$siteName}");
        $this->line("  Project root: {$this->projectRoot}");
    }

    /**
     * Get the ASCII logo.
     */
    protected function getLogo(): string
    {
        return <<<'LOGO'
   ███████╗██╗   ██╗██████╗ ███████╗████████╗██████╗  █████╗ ████████╗███████╗
   ██╔════╝██║   ██║██╔══██╗██╔════╝╚══██╔══╝██╔══██╗██╔══██╗╚══██╔══╝██╔════╝
   ███████╗██║   ██║██████╔╝███████╗   ██║   ██████╔╝███████║   ██║   █████╗
   ╚════██║██║   ██║██╔══██╗╚════██║   ██║   ██╔══██╗██╔══██║   ██║   ██╔══╝
   ███████║╚██████╔╝██████╔╝███████║   ██║   ██║  ██║██║  ██║   ██║   ███████╗
   ╚══════╝ ╚═════╝ ╚═════╝ ╚══════╝   ╚═╝   ╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝   ╚══════╝
LOGO;
    }

    /**
     * Discover the environment.
     */
    protected function discoverEnvironment(): void
    {
        $this->newLine();
        info('Analyzing your environment...');

        $checks = [];

        // Check WordPress
        global $wp_version;
        $checks[] = "WordPress: ".($wp_version ?? 'Not loaded');

        // Check Acorn
        $acornVersion = defined('Roots\\Acorn\\Application::VERSION')
            ? \Roots\Acorn\Application::VERSION
            : $this->getPackageVersion('roots/acorn');
        $checks[] = "Acorn: {$acornVersion}";

        // Check Sage theme
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            $checks[] = "Theme: ".$theme->get('Name')." v".$theme->get('Version');
        }

        // Check for ACF
        if (function_exists('acf_get_field_groups')) {
            $checks[] = "ACF: ".(defined('ACF_VERSION') ? ACF_VERSION : 'Active');
        }

        // Check for WooCommerce
        if (class_exists('WooCommerce')) {
            $checks[] = "WooCommerce: ".(defined('WC_VERSION') ? WC_VERSION : 'Active');
        }

        foreach ($checks as $check) {
            $this->line("  ✓ {$check}");
        }

        $this->newLine();
    }

    /**
     * Install the MCP configuration.
     */
    protected function installMcpConfig(): void
    {
        info('Installing MCP configuration...');

        $targets = multiselect(
            label: 'Which MCP clients should be configured?',
            options: [
                'project' => 'Project .mcp.json (Claude Code, Cursor)',
                'vscode' => 'VS Code settings.json',
            ],
            default: ['project'],
            hint: 'Select all that apply',
        );

        foreach ($targets as $target) {
            match ($target) {
                'project' => $this->writeMcpJson(),
                'vscode' => $this->writeVsCodeConfig(),
                default => null,
            };
        }

        $this->newLine();
    }

    /**
     * Write the .mcp.json file.
     */
    protected function writeMcpJson(): void
    {
        $mcpPath = $this->projectRoot.'/.mcp.json';

        $config = [
            'mcpServers' => [
                'substrate' => [
                    'command' => 'php',
                    'args' => [
                        'vendor/bin/acorn',
                        'substrate:mcp',
                    ],
                ],
            ],
        ];

        // Merge with existing config if present
        if (file_exists($mcpPath)) {
            $existing = json_decode(file_get_contents($mcpPath), true) ?? [];
            $config['mcpServers'] = array_merge(
                $existing['mcpServers'] ?? [],
                $config['mcpServers']
            );
        }

        File::put($mcpPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->line("  ✓ Created .mcp.json");
    }

    /**
     * Write VS Code configuration.
     */
    protected function writeVsCodeConfig(): void
    {
        $vscodePath = $this->projectRoot.'/.vscode';
        $settingsPath = $vscodePath.'/settings.json';

        if (! is_dir($vscodePath)) {
            mkdir($vscodePath, 0755, true);
        }

        $mcpConfig = [
            'mcp' => [
                'servers' => [
                    'substrate' => [
                        'command' => 'php',
                        'args' => ['vendor/bin/acorn', 'substrate:mcp'],
                    ],
                ],
            ],
        ];

        // Merge with existing settings if present
        $settings = [];
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true) ?? [];
        }

        $settings = array_merge($settings, $mcpConfig);

        File::put($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->line("  ✓ Updated .vscode/settings.json");
    }

    /**
     * Install AI guidelines.
     */
    protected function installGuidelines(): void
    {
        info('Installing AI guidelines...');

        $guidelinesDir = $this->projectRoot.'/.ai';
        if (! is_dir($guidelinesDir)) {
            mkdir($guidelinesDir, 0755, true);
        }

        // Create main guidelines file
        $guidelines = $this->composeGuidelines();
        File::put($guidelinesDir.'/guidelines.md', $guidelines);
        $this->line("  ✓ Created .ai/guidelines.md");

        // Create CLAUDE.md for Claude Code
        if (confirm('Create CLAUDE.md for Claude Code?', true)) {
            File::put($this->projectRoot.'/CLAUDE.md', $this->composeClaudeMd());
            $this->line("  ✓ Created CLAUDE.md");
        }

        $this->newLine();
    }

    /**
     * Compose the AI guidelines content.
     */
    protected function composeGuidelines(): string
    {
        $siteName = function_exists('get_bloginfo')
            ? get_bloginfo('name')
            : 'WordPress Project';

        $guidelines = <<<MARKDOWN
# AI Development Guidelines for {$siteName}

## Project Stack

This project uses the Roots stack:
- **WordPress** - Content management system
- **Bedrock** - Modern WordPress project structure with Composer
- **Acorn** - Laravel components for WordPress (Service Providers, Blade, etc.)
- **Sage** - WordPress starter theme with Blade templates

## Directory Structure

```
├── config/                 # Bedrock configuration
├── web/
│   ├── app/
│   │   ├── themes/        # WordPress themes
│   │   │   └── sage/      # Sage theme
│   │   │       ├── app/   # Theme PHP code
│   │   │       │   ├── Providers/
│   │   │       │   └── View/Composers/
│   │   │       └── resources/
│   │   │           ├── views/     # Blade templates
│   │   │           ├── js/        # JavaScript
│   │   │           └── css/       # Stylesheets
│   │   ├── plugins/       # WordPress plugins
│   │   └── mu-plugins/    # Must-use plugins
│   └── wp/                # WordPress core
└── vendor/                # Composer dependencies
```

## Coding Standards

### PHP
- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Type-hint all parameters and return types
- Use WordPress coding standards for hooks and filters

### Blade Templates
- Use Blade syntax, not PHP tags
- Leverage View Composers for data
- Create components for reusable UI
- Use `@php` directive for WordPress functions

### JavaScript
- Use ES6+ syntax
- Import modules properly
- Handle errors gracefully

## WordPress Patterns

### Hooks
```php
add_action('init', function () {
    // Registration code
});

add_filter('the_content', function (\$content) {
    return \$content;
});
```

### Custom Post Types
Register in a Service Provider's `boot()` method.

### View Composers
```php
namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class Example extends Composer
{
    protected static \$views = ['partials.example'];

    public function with(): array
    {
        return ['data' => \$this->getData()];
    }
}
```

## Security

- Sanitize all user input
- Escape all output
- Use nonces for forms
- Never trust user data
- Use prepared statements for database queries

## Testing

Run tests with:
```bash
vendor/bin/pest
```

MARKDOWN;

        return $guidelines;
    }

    /**
     * Compose CLAUDE.md content.
     */
    protected function composeClaudeMd(): string
    {
        return <<<'MARKDOWN'
# Claude Code Instructions

This is a WordPress project using the Roots stack (Bedrock, Acorn, Sage).

## Quick Reference

- **Theme path**: `web/app/themes/sage/`
- **Views**: `resources/views/` (Blade templates)
- **Composers**: `app/View/Composers/`
- **Providers**: `app/Providers/`

## MCP Server

This project has Substrate MCP server configured. Use these tools:
- `ApplicationInfo` - Get project info
- `DatabaseSchema` - View database structure
- `ListPostTypes` - See registered post types
- `ListBlocks` - See Gutenberg blocks
- `SageInfo` - Get theme details

## Guidelines

See `.ai/guidelines.md` for full development guidelines.

MARKDOWN;
    }

    /**
     * Display the outro message.
     */
    protected function displayOutro(): void
    {
        outro('Substrate installation complete!');

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Restart your IDE/editor to load MCP configuration');
        $this->line('  2. Start using Substrate tools in your AI assistant');
        $this->line('  3. Customize .ai/guidelines.md for your project');
        $this->newLine();
    }

    /**
     * Get a package version from composer.lock.
     */
    protected function getPackageVersion(string $package): string
    {
        $lockFile = $this->projectRoot.'/composer.lock';

        if (! file_exists($lockFile)) {
            return 'unknown';
        }

        $lock = json_decode(file_get_contents($lockFile), true);

        foreach ($lock['packages'] ?? [] as $pkg) {
            if ($pkg['name'] === $package) {
                return $pkg['version'] ?? 'unknown';
            }
        }

        return 'unknown';
    }
}
