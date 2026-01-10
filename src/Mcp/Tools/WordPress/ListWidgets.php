<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListWidgets extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all registered WordPress widgets and sidebars (widget areas), including which widgets are active in each sidebar.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        global $wp_widget_factory, $wp_registered_sidebars, $wp_registered_widgets;

        // Get registered widgets
        $widgets = [];
        if (isset($wp_widget_factory) && isset($wp_widget_factory->widgets)) {
            foreach ($wp_widget_factory->widgets as $widgetClass => $widget) {
                $widgets[$widget->id_base] = [
                    'id_base' => $widget->id_base,
                    'name' => $widget->name,
                    'class' => $widgetClass,
                    'description' => $widget->widget_options['description'] ?? '',
                ];
            }
        }

        // Get registered sidebars
        $sidebars = [];
        if (is_array($wp_registered_sidebars)) {
            foreach ($wp_registered_sidebars as $sidebarId => $sidebar) {
                $sidebars[$sidebarId] = [
                    'id' => $sidebarId,
                    'name' => $sidebar['name'],
                    'description' => $sidebar['description'] ?? '',
                    'class' => $sidebar['class'] ?? '',
                    'before_widget' => $sidebar['before_widget'] ?? '',
                    'after_widget' => $sidebar['after_widget'] ?? '',
                ];
            }
        }

        // Get active widgets per sidebar
        $sidebarsWidgets = wp_get_sidebars_widgets();
        $activeWidgets = [];

        foreach ($sidebarsWidgets as $sidebarId => $widgetIds) {
            if ($sidebarId === 'wp_inactive_widgets' || ! is_array($widgetIds)) {
                continue;
            }

            $activeWidgets[$sidebarId] = [];
            foreach ($widgetIds as $widgetId) {
                // Extract widget base from ID (e.g., "text-2" -> "text")
                $baseName = preg_replace('/-\d+$/', '', $widgetId);
                $activeWidgets[$sidebarId][] = [
                    'id' => $widgetId,
                    'type' => $baseName,
                    'name' => $wp_registered_widgets[$widgetId]['name'] ?? $baseName,
                ];
            }
        }

        return Response::json([
            'widgets' => $widgets,
            'sidebars' => $sidebars,
            'active_widgets' => $activeWidgets,
            'counts' => [
                'widgets' => count($widgets),
                'sidebars' => count($sidebars),
            ],
        ]);
    }
}
