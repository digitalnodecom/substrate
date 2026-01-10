<?php

declare(strict_types=1);

namespace Roots\Substrate\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait MakesHttpRequests
{
    /**
     * Get an HTTP client instance.
     */
    public function client(): PendingRequest
    {
        $client = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 Substrate/1.0 WordPress MCP Server',
        ]);

        // Disable SSL verification for local development
        if (function_exists('wp_get_environment_type')) {
            $env = wp_get_environment_type();
            if (in_array($env, ['local', 'development'], true)) {
                return $client->withoutVerifying();
            }
        }

        return $client;
    }

    /**
     * Make a GET request.
     */
    public function get(string $url): Response
    {
        return $this->client()->get($url);
    }

    /**
     * Make a JSON POST request.
     *
     * @param  array<string, mixed>  $json
     */
    public function json(string $url, array $json): Response
    {
        return $this->client()->asJson()->post($url, $json);
    }
}
