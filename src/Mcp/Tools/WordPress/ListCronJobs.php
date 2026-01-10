<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Tools\WordPress;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListCronJobs extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all scheduled WordPress cron jobs with their next run time, schedule, and callbacks.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $cronJobs = _get_cron_array();

        if (! is_array($cronJobs) || empty($cronJobs)) {
            return Response::json([
                'count' => 0,
                'schedules' => wp_get_schedules(),
                'jobs' => [],
            ]);
        }

        $result = [];
        $now = time();

        foreach ($cronJobs as $timestamp => $hooks) {
            foreach ($hooks as $hookName => $events) {
                foreach ($events as $key => $event) {
                    $schedule = $event['schedule'] ?? false;
                    $scheduleInfo = $schedule ? (wp_get_schedules()[$schedule] ?? null) : null;

                    $result[] = [
                        'hook' => $hookName,
                        'next_run' => date('Y-m-d H:i:s', $timestamp),
                        'next_run_timestamp' => $timestamp,
                        'seconds_until_run' => $timestamp - $now,
                        'schedule' => $schedule ?: 'single',
                        'interval' => $event['interval'] ?? null,
                        'interval_display' => $scheduleInfo['display'] ?? ($schedule ?: 'One-time'),
                        'args' => $event['args'] ?? [],
                    ];
                }
            }
        }

        // Sort by next run time
        usort($result, fn ($a, $b) => $a['next_run_timestamp'] - $b['next_run_timestamp']);

        return Response::json([
            'count' => count($result),
            'doing_cron' => defined('DOING_CRON') && DOING_CRON,
            'schedules' => wp_get_schedules(),
            'jobs' => $result,
        ]);
    }
}
