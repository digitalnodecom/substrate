<?php

declare(strict_types=1);

namespace Roots\Substrate\Console;

use Illuminate\Console\Command;
use Roots\Substrate\Mcp\ToolRegistry;

class UpdateCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'substrate:update';

    /**
     * The console command description.
     */
    protected $description = 'Update Substrate caches and regenerate guidelines';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Updating Substrate...');
        $this->newLine();

        // Clear tool registry cache
        ToolRegistry::clearCache();
        $this->line('  ✓ Cleared tool registry cache');

        // Clear any cached data
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $this->line('  ✓ Flushed WordPress object cache');
        }

        // Clear Substrate-specific caches
        $cacheKeys = [
            'substrate:database-schema:*',
            'substrate:last_error',
        ];

        foreach ($cacheKeys as $pattern) {
            cache()->forget($pattern);
        }
        $this->line('  ✓ Cleared Substrate caches');

        // Show current tool count
        $tools = ToolRegistry::getAvailableTools();
        $this->newLine();
        $this->info(sprintf('Substrate updated! %d tools available.', count($tools)));

        // List tool categories
        $categories = [
            'Core' => 0,
            'WordPress' => 0,
            'Acorn' => 0,
        ];

        foreach ($tools as $tool) {
            if (str_contains($tool, '\\Core\\')) {
                $categories['Core']++;
            } elseif (str_contains($tool, '\\WordPress\\')) {
                $categories['WordPress']++;
            } elseif (str_contains($tool, '\\Acorn\\')) {
                $categories['Acorn']++;
            }
        }

        foreach ($categories as $category => $count) {
            $this->line("  - {$category}: {$count} tools");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
