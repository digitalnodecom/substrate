<?php

declare(strict_types=1);

namespace Roots\Substrate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('substrate:mcp', 'Starts the Substrate MCP server')]
class StartCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'substrate:mcp';

    /**
     * The console command description.
     */
    protected $description = 'Starts the Substrate MCP server (usually invoked from mcp.json)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return Artisan::call('mcp:start substrate');
    }
}
