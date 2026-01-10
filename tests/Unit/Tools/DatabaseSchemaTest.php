<?php

declare(strict_types=1);

use Roots\Substrate\Mcp\Tools\Core\DatabaseSchema;

test('database schema tool has description', function () {
    $tool = new DatabaseSchema();
    $reflection = new ReflectionClass($tool);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);

    expect($property->getValue($tool))->toBeString()->not->toBeEmpty();
});

test('database schema tool has schema method', function () {
    $tool = new DatabaseSchema();

    expect(method_exists($tool, 'schema'))->toBeTrue();
});

test('database schema tool has handle method', function () {
    $tool = new DatabaseSchema();

    expect(method_exists($tool, 'handle'))->toBeTrue();
});
