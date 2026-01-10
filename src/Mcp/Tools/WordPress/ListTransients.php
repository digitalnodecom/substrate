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
class ListTransients extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List WordPress transients stored in the database with their expiration times. Note: This only shows database-stored transients, not those in object cache.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Search transients by name pattern'),
            'expired' => $schema->boolean()
                ->description('Include expired transients (default: false)'),
            'limit' => $schema->integer()
                ->description('Maximum number of transients to return (default: 100)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wpdb;

        $search = $request->get('search', '');
        $includeExpired = (bool) $request->get('expired', false);
        $limit = (int) $request->get('limit', 100);

        $now = time();

        // Build query
        $query = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'";

        if ($search) {
            $query .= $wpdb->prepare(' AND option_name LIKE %s', '%'.$wpdb->esc_like($search).'%');
        }

        $query .= " LIMIT {$limit}";

        $transients = $wpdb->get_results($query, ARRAY_A);
        $result = [];

        foreach ($transients as $row) {
            $name = $row['option_name'];

            // Skip timeout entries - we'll match them with their transients
            if (str_contains($name, '_transient_timeout_')) {
                continue;
            }

            // Extract transient name
            $transientName = str_replace('_transient_', '', $name);
            $transientName = str_replace('_site_', '', $transientName);

            // Get expiration
            $timeoutKey = '_transient_timeout_'.$transientName;
            $expiration = get_option($timeoutKey);

            $isExpired = $expiration && $expiration < $now;

            // Skip expired if not requested
            if ($isExpired && ! $includeExpired) {
                continue;
            }

            $value = maybe_unserialize($row['option_value']);
            $valueType = gettype($value);
            $valueSize = strlen($row['option_value']);

            $result[$transientName] = [
                'name' => $transientName,
                'expires' => $expiration ? date('Y-m-d H:i:s', (int) $expiration) : 'never',
                'expires_in' => $expiration ? ((int) $expiration - $now) : null,
                'is_expired' => $isExpired,
                'value_type' => $valueType,
                'value_size' => $valueSize,
                'value_preview' => $this->getValuePreview($value),
            ];
        }

        return Response::json([
            'count' => count($result),
            'transients' => $result,
        ]);
    }

    /**
     * Get a preview of the transient value.
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
