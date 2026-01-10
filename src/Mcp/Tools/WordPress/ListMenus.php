<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMenus extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress navigation menus, menu locations, and their assigned menus.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if (! function_exists('get_registered_nav_menus')) {
            return Response::error('WordPress menu functions not available.');
        }

        // Get registered menu locations
        $locations = get_registered_nav_menus();
        $assignedMenus = get_nav_menu_locations();

        // Get all menus
        $menus = wp_get_nav_menus();
        $menuList = [];

        foreach ($menus as $menu) {
            $menuItems = wp_get_nav_menu_items($menu->term_id);

            $menuList[$menu->slug] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'description' => $menu->description,
                'item_count' => is_array($menuItems) ? count($menuItems) : 0,
            ];
        }

        // Map locations to menus
        $locationMap = [];
        foreach ($locations as $location => $description) {
            $menuId = $assignedMenus[$location] ?? null;
            $menu = $menuId ? wp_get_nav_menu_object($menuId) : null;

            $locationMap[$location] = [
                'location' => $location,
                'description' => $description,
                'assigned_menu' => $menu ? $menu->slug : null,
                'assigned_menu_id' => $menuId,
            ];
        }

        return Response::json([
            'locations' => $locationMap,
            'menus' => $menuList,
            'counts' => [
                'locations' => count($locations),
                'menus' => count($menuList),
            ],
        ]);
    }
}
