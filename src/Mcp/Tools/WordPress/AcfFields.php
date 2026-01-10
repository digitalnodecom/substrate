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
class AcfFields extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List Advanced Custom Fields (ACF) field groups and their fields. Only available if ACF plugin is installed and active.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'group' => $schema->string()
                ->description('Filter by field group name or key'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Check if ACF is available
        if (! function_exists('acf_get_field_groups')) {
            return Response::error('Advanced Custom Fields (ACF) plugin is not installed or not active.');
        }

        $groupFilter = $request->get('group');

        $fieldGroups = acf_get_field_groups();
        $result = [];

        foreach ($fieldGroups as $group) {
            // Apply filter
            if ($groupFilter) {
                if (stripos($group['title'], $groupFilter) === false && stripos($group['key'], $groupFilter) === false) {
                    continue;
                }
            }

            $fields = acf_get_fields($group['key']);
            $fieldData = [];

            if (is_array($fields)) {
                foreach ($fields as $field) {
                    $fieldData[$field['name']] = $this->formatField($field);
                }
            }

            $result[$group['key']] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'active' => $group['active'],
                'menu_order' => $group['menu_order'],
                'location' => $this->formatLocation($group['location'] ?? []),
                'fields' => $fieldData,
                'field_count' => count($fieldData),
            ];
        }

        return Response::json([
            'acf_version' => defined('ACF_VERSION') ? ACF_VERSION : 'unknown',
            'count' => count($result),
            'field_groups' => $result,
        ]);
    }

    /**
     * Format a field for output.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function formatField(array $field): array
    {
        $formatted = [
            'key' => $field['key'] ?? '',
            'name' => $field['name'] ?? '',
            'label' => $field['label'] ?? '',
            'type' => $field['type'] ?? '',
            'required' => $field['required'] ?? false,
            'instructions' => $field['instructions'] ?? '',
        ];

        // Add type-specific properties
        switch ($field['type'] ?? '') {
            case 'text':
            case 'textarea':
                $formatted['placeholder'] = $field['placeholder'] ?? '';
                $formatted['maxlength'] = $field['maxlength'] ?? '';
                break;

            case 'select':
            case 'checkbox':
            case 'radio':
                $formatted['choices'] = $field['choices'] ?? [];
                $formatted['multiple'] = $field['multiple'] ?? false;
                break;

            case 'image':
            case 'file':
                $formatted['return_format'] = $field['return_format'] ?? 'array';
                $formatted['mime_types'] = $field['mime_types'] ?? '';
                break;

            case 'relationship':
            case 'post_object':
                $formatted['post_type'] = $field['post_type'] ?? [];
                $formatted['return_format'] = $field['return_format'] ?? 'object';
                break;

            case 'repeater':
            case 'flexible_content':
            case 'group':
                $subFields = $field['sub_fields'] ?? [];
                $formatted['sub_fields'] = [];
                foreach ($subFields as $subField) {
                    $formatted['sub_fields'][$subField['name']] = $this->formatField($subField);
                }
                break;
        }

        return $formatted;
    }

    /**
     * Format location rules for output.
     *
     * @param  array<int, array<int, array<string, string>>>  $location
     * @return array<int, array<int, array<string, string>>>
     */
    protected function formatLocation(array $location): array
    {
        $formatted = [];

        foreach ($location as $group) {
            $rules = [];
            foreach ($group as $rule) {
                $rules[] = [
                    'param' => $rule['param'] ?? '',
                    'operator' => $rule['operator'] ?? '',
                    'value' => $rule['value'] ?? '',
                ];
            }
            $formatted[] = $rules;
        }

        return $formatted;
    }
}
