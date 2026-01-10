<?php

declare(strict_types=1);

test('install command is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('substrate:install')
        ->assertExitCode(0);
});

test('update command is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('substrate:update')
        ->assertExitCode(0);
});

test('mcp command is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('substrate:mcp')
        ->assertExitCode(0);
});
