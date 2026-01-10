<?php

declare(strict_types=1);

use Roots\Substrate\SubstrateManager;

test('can instantiate substrate manager', function () {
    $manager = new SubstrateManager();

    expect($manager)->toBeInstanceOf(SubstrateManager::class);
});

test('substrate manager has version', function () {
    $manager = new SubstrateManager();

    expect($manager->version())->toBeString();
});

test('can check if substrate is enabled', function () {
    $manager = new SubstrateManager();

    expect($manager->enabled())->toBeBool();
});
