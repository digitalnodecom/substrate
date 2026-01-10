<?php

declare(strict_types=1);

use Roots\Substrate\Mcp\Tools\Core\ApplicationInfo;

test('application info tool has description', function () {
    $tool = new ApplicationInfo();
    $reflection = new ReflectionClass($tool);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);

    expect($property->getValue($tool))->toBeString()->not->toBeEmpty();
});

test('application info tool is read only', function () {
    $tool = new ApplicationInfo();
    $reflection = new ReflectionClass($tool);
    $attributes = $reflection->getAttributes();

    $isReadOnly = false;
    foreach ($attributes as $attribute) {
        if (str_contains($attribute->getName(), 'IsReadOnly')) {
            $isReadOnly = true;
            break;
        }
    }

    expect($isReadOnly)->toBeTrue();
});
