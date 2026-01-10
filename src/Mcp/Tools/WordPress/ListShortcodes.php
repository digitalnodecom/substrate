<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListShortcodes extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress shortcodes with their callback information.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $shortcode_tags;

        if (! is_array($shortcode_tags) || empty($shortcode_tags)) {
            return Response::json([
                'count' => 0,
                'shortcodes' => [],
            ]);
        }

        $result = [];

        foreach ($shortcode_tags as $tag => $callback) {
            $callbackInfo = $this->getCallbackInfo($callback);

            $result[$tag] = [
                'tag' => $tag,
                'callback' => $callbackInfo['name'],
                'type' => $callbackInfo['type'],
                'class' => $callbackInfo['class'],
            ];
        }

        ksort($result);

        return Response::json([
            'count' => count($result),
            'shortcodes' => $result,
        ]);
    }

    /**
     * Get callback information.
     *
     * @param  mixed  $callback
     * @return array<string, string|null>
     */
    protected function getCallbackInfo($callback): array
    {
        if (is_string($callback)) {
            return [
                'name' => $callback,
                'type' => 'function',
                'class' => null,
            ];
        }

        if (is_array($callback) && count($callback) === 2) {
            $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
            $method = $callback[1];

            return [
                'name' => "{$class}::{$method}",
                'type' => 'method',
                'class' => $class,
            ];
        }

        if (is_object($callback) && $callback instanceof \Closure) {
            return [
                'name' => 'Closure',
                'type' => 'closure',
                'class' => null,
            ];
        }

        return [
            'name' => 'unknown',
            'type' => 'unknown',
            'class' => null,
        ];
    }
}
