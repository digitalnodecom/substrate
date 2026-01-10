<?php

declare(strict_types=1);

use Roots\Substrate\Services\BrowserLogger;

test('browser logger generates script', function () {
    $script = BrowserLogger::getScript();

    expect($script)->toBeString()->toContain('<script>')->toContain('console');
});

test('browser logger script contains endpoint', function () {
    $script = BrowserLogger::getScript();

    expect($script)->toContain('/_substrate/browser-logs');
});

test('browser logger script intercepts console methods', function () {
    $script = BrowserLogger::getScript();

    expect($script)
        ->toContain('console.log')
        ->toContain('console.warn')
        ->toContain('console.error');
});
