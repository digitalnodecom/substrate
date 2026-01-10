<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListHooks extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List WordPress action and filter hooks that have callbacks registered. Useful for debugging and understanding plugin/theme behavior.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Search for hooks by name pattern (e.g., "init", "wp_head", "woocommerce")'),
            'limit' => $schema->integer()
                ->description('Maximum number of hooks to return (default: 100)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wp_filter;

        if (! is_array($wp_filter) || empty($wp_filter)) {
            return Response::json([
                'count' => 0,
                'hooks' => [],
            ]);
        }

        $search = $request->get('search', '');
        $limit = (int) $request->get('limit', 100);

        $result = [];
        $count = 0;

        foreach ($wp_filter as $hookName => $hookObject) {
            // Apply search filter
            if ($search && stripos($hookName, $search) === false) {
                continue;
            }

            // Get callbacks for this hook
            $callbacks = [];
            if (is_object($hookObject) && isset($hookObject->callbacks)) {
                foreach ($hookObject->callbacks as $priority => $priorityCallbacks) {
                    foreach ($priorityCallbacks as $callbackId => $callbackData) {
                        $callbacks[] = [
                            'priority' => $priority,
                            'callback' => $this->getCallbackName($callbackData['function'] ?? null),
                            'accepted_args' => $callbackData['accepted_args'] ?? 1,
                        ];
                    }
                }
            }

            if (empty($callbacks)) {
                continue;
            }

            $result[$hookName] = [
                'hook' => $hookName,
                'callback_count' => count($callbacks),
                'callbacks' => $callbacks,
            ];

            $count++;
            if ($count >= $limit) {
                break;
            }
        }

        return Response::json([
            'count' => count($result),
            'total_hooks' => count($wp_filter),
            'hooks' => $result,
        ]);
    }

    /**
     * Get a readable callback name.
     *
     * @param  mixed  $callback
     */
    protected function getCallbackName($callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            $method = $callback[1];

            return "{$class}::{$method}";
        }

        if (is_object($callback) && $callback instanceof \Closure) {
            return 'Closure';
        }

        return 'unknown';
    }
}
