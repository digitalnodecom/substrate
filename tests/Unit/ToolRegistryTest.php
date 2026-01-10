<?php

declare(strict_types=1);

use Roots\Substrate\Mcp\ToolRegistry;

beforeEach(function () {
    ToolRegistry::clearCache();
});

test('can get available tools', function () {
    $tools = ToolRegistry::getAvailableTools();

    expect($tools)->toBeArray();
});

test('can get tool class by name', function () {
    $tools = ToolRegistry::getAvailableTools();

    if (empty($tools)) {
        $this->markTestSkipped('No tools registered');
    }

    $firstTool = array_key_first($tools);
    $toolClass = ToolRegistry::getToolClass($firstTool);

    expect($toolClass)->toBeString()->toContain('\\');
});

test('returns null for unknown tool', function () {
    $toolClass = ToolRegistry::getToolClass('NonExistentTool');

    expect($toolClass)->toBeNull();
});

test('cache can be cleared', function () {
    // Get tools to populate cache
    $tools1 = ToolRegistry::getAvailableTools();

    // Clear cache
    ToolRegistry::clearCache();

    // Get tools again
    $tools2 = ToolRegistry::getAvailableTools();

    expect($tools1)->toEqual($tools2);
});
