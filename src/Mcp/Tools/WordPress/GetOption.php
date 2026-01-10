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
class GetOption extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get the value of a WordPress option from the database. Can also list all options matching a pattern.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The option name to retrieve, or a pattern with % wildcards to list matching options')
                ->required(),
            'list' => $schema->boolean()
                ->description('If true, treat name as a pattern and list all matching options'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wpdb;

        $name = $request->get('name');
        $listMode = (bool) $request->get('list', false);

        // If listing mode or contains wildcards
        if ($listMode || str_contains($name, '%')) {
            return $this->listOptions($name);
        }

        // Get single option
        $value = get_option($name);

        if ($value === false) {
            // Check if option exists but is actually false
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                    $name
                )
            );

            if (! $exists) {
                return Response::error("Option '{$name}' not found.");
            }
        }

        return Response::json([
            'name' => $name,
            'value' => $value,
            'type' => gettype($value),
            'autoload' => $this->getAutoloadStatus($name),
        ]);
    }

    /**
     * List options matching a pattern.
     */
    protected function listOptions(string $pattern): Response
    {
        global $wpdb;

        // Ensure pattern has wildcards
        if (! str_contains($pattern, '%')) {
            $pattern = '%'.$pattern.'%';
        }

        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100",
                $pattern
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($options as $option) {
            $value = maybe_unserialize($option['option_value']);

            $result[$option['option_name']] = [
                'name' => $option['option_name'],
                'value_preview' => $this->getValuePreview($value),
                'type' => gettype($value),
                'autoload' => $option['autoload'] === 'yes',
            ];
        }

        return Response::json([
            'pattern' => $pattern,
            'count' => count($result),
            'options' => $result,
        ]);
    }

    /**
     * Get autoload status for an option.
     */
    protected function getAutoloadStatus(string $name): bool
    {
        global $wpdb;

        $autoload = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                $name
            )
        );

        return $autoload === 'yes';
    }

    /**
     * Get a preview of the option value.
     *
     * @param  mixed  $value
     */
    protected function getValuePreview($value): string
    {
        if (is_string($value)) {
            return strlen($value) > 100 ? substr($value, 0, 100).'...' : $value;
        }

        if (is_array($value)) {
            return 'Array('.count($value).' items)';
        }

        if (is_object($value)) {
            return 'Object('.get_class($value).')';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }
}
