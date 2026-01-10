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
class ListUsers extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List WordPress user roles and their capabilities. For privacy, returns role information rather than individual user data.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_counts' => $schema->boolean()
                ->description('Include user counts per role (default: true)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wp_roles;

        if (! isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $includeCounts = (bool) $request->get('include_counts', true);

        $roles = [];
        $roleCounts = [];

        if ($includeCounts) {
            $roleCounts = count_users()['avail_roles'] ?? [];
        }

        foreach ($wp_roles->roles as $roleSlug => $roleData) {
            $roles[$roleSlug] = [
                'name' => $roleSlug,
                'display_name' => $roleData['name'] ?? $roleSlug,
                'capabilities' => array_keys(array_filter($roleData['capabilities'] ?? [])),
                'user_count' => $includeCounts ? ($roleCounts[$roleSlug] ?? 0) : null,
            ];
        }

        // Get total user count
        $totalUsers = $includeCounts ? (count_users()['total_users'] ?? 0) : null;

        return Response::json([
            'total_users' => $totalUsers,
            'role_count' => count($roles),
            'roles' => $roles,
        ]);
    }
}
