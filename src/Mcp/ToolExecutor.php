<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp;

use Laravel\Mcp\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ToolExecutor
{
    /**
     * The project root directory.
     */
    protected string $projectRoot;

    /**
     * Create a new ToolExecutor instance.
     */
    public function __construct()
    {
        $this->projectRoot = $this->detectProjectRoot();
    }

    /**
     * Execute a tool in a subprocess.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolClass, array $arguments = []): Response
    {
        return $this->executeInSubprocess($toolClass, $arguments);
    }

    /**
     * Detect the project root directory (Bedrock root).
     */
    protected function detectProjectRoot(): string
    {
        // Walk up from theme directory looking for Bedrock indicators
        $path = base_path();
        while ($path !== dirname($path)) {
            if (file_exists($path.'/wp-cli.yml') || file_exists($path.'/config/application.php')) {
                return $path;
            }
            $path = dirname($path);
        }

        // Fallback to base_path if no Bedrock root found
        return base_path();
    }

    /**
     * Execute the tool in a subprocess for isolation.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function executeInSubprocess(string $toolClass, array $arguments): Response
    {
        $command = $this->buildCommand($toolClass, $arguments);

        // Clear environment variables that would pollute the subprocess
        $cleanEnv = $this->getCleanEnvironment();

        $process = new Process(
            command: $command,
            cwd: $this->projectRoot,
            env: $cleanEnv,
            timeout: $this->getTimeout($arguments)
        );

        try {
            $process->mustRun();

            $output = $process->getOutput();
            $decoded = json_decode($output, true);

            // If JSON decode fails, try to extract JSON from the output in case
            // PHP notices/warnings were prepended before the JSON payload.
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonStart = strpos($output, '{"');
                if ($jsonStart !== false && $jsonStart > 0) {
                    $decoded = json_decode(substr($output, $jsonStart), true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::error('Invalid JSON output from tool process: '.json_last_error_msg());
            }

            return $this->reconstructResponse($decoded);
        } catch (ProcessTimedOutException) {
            $process->stop();

            return Response::error("Tool execution timed out after {$this->getTimeout($arguments)} seconds");
        } catch (ProcessFailedException) {
            $errorOutput = $process->getErrorOutput().$process->getOutput();

            return Response::error("Process tool execution failed: {$errorOutput}");
        }
    }

    /**
     * Get a clean environment for the subprocess.
     *
     * Returning null inherits the parent process environment, allowing the
     * subprocess to read .env via the Acorn bootstrap. Previously, all .env
     * keys were set to false (unset), which stripped DB credentials and other
     * vars needed for WordPress to boot — causing silent failures.
     *
     * @return array<string, mixed>|null
     */
    protected function getCleanEnvironment(): ?array
    {
        return null;
    }

    /**
     * Get the timeout for the tool execution.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function getTimeout(array $arguments): int
    {
        $timeout = (int) ($arguments['timeout'] ?? 180);

        return max(1, min(600, $timeout));
    }

    /**
     * Reconstruct a Response from JSON data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function reconstructResponse(array $data): Response
    {
        if (! isset($data['isError']) || ! isset($data['content'])) {
            return Response::error('Invalid tool response format.');
        }

        if ($data['isError']) {
            $errorText = 'Unknown error';

            if (is_array($data['content']) && ! empty($data['content'])) {
                $firstContent = $data['content'][0] ?? [];
                if (is_array($firstContent)) {
                    $errorText = $firstContent['text'] ?? $errorText;
                }
            }

            return Response::error($errorText);
        }

        // Handle array format - extract text content
        if (is_array($data['content']) && ! empty($data['content'])) {
            $firstContent = $data['content'][0] ?? [];

            if (is_array($firstContent)) {
                $text = $firstContent['text'] ?? '';

                $decoded = json_decode((string) $text, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return Response::json($decoded);
                }

                return Response::text($text);
            }
        }

        return Response::text('');
    }

    /**
     * Build the command array for executing a tool in a subprocess.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string>
     */
    protected function buildCommand(string $toolClass, array $arguments): array
    {
        $encodedClass = base64_encode($toolClass);
        $encodedArgs = base64_encode(json_encode($arguments));

        // In a Bedrock/WordPress project, Acorn must be bootstrapped through
        // WP-CLI (`wp acorn`) so that WordPress is loaded. Running
        // `php vendor/bin/acorn` standalone does not boot WordPress, causing
        // tools that depend on WP functions to produce empty output.
        if ($this->shouldUseWpCli()) {
            return [
                $this->findWpCli(),
                'acorn',
                'substrate:execute-tool',
                $encodedClass,
                $encodedArgs,
            ];
        }

        return [
            PHP_BINARY,
            $this->projectRoot.'/vendor/bin/acorn',
            'substrate:execute-tool',
            $encodedClass,
            $encodedArgs,
        ];
    }

    /**
     * Determine if WP-CLI should be used to bootstrap the subprocess.
     */
    protected function shouldUseWpCli(): bool
    {
        return file_exists($this->projectRoot.'/wp-cli.yml')
            || file_exists($this->projectRoot.'/wp-cli.local.yml')
            || function_exists('add_action');
    }

    /**
     * Find the WP-CLI binary path.
     */
    protected function findWpCli(): string
    {
        // Check common locations
        foreach (['/opt/homebrew/bin/wp', '/usr/local/bin/wp', '/usr/bin/wp'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fall back to PATH resolution
        return 'wp';
    }
}
