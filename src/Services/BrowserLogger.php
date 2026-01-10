<?php

declare(strict_types=1);

namespace Roots\Substrate\Services;

class BrowserLogger
{
    /**
     * Get the JavaScript code for browser logging.
     */
    public static function getScript(): string
    {
        $endpoint = url('/_substrate/browser-logs');

        return <<<JAVASCRIPT
<script>
(function() {
    const logs = [];
    let debounceTimer = null;

    function sendLogs() {
        if (logs.length === 0) return;

        const logsToSend = [...logs];
        logs.length = 0;

        fetch('{$endpoint}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ logs: logsToSend })
        }).catch(function() {
            // Silently fail
        });
    }

    function queueLog(type, data) {
        logs.push({
            type: type,
            timestamp: new Date().toISOString(),
            data: data,
            url: window.location.href,
            userAgent: navigator.userAgent
        });

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(sendLogs, 100);
    }

    // Intercept console methods
    ['log', 'info', 'warn', 'error', 'table'].forEach(function(method) {
        const original = console[method];
        console[method] = function() {
            const args = Array.from(arguments).map(function(arg) {
                try {
                    if (typeof arg === 'object') {
                        return JSON.stringify(arg);
                    }
                    return String(arg);
                } catch (e) {
                    return String(arg);
                }
            });
            queueLog(method, args);
            return original.apply(console, arguments);
        };
    });

    // Capture window errors
    window.addEventListener('error', function(event) {
        queueLog('window_error', [event.message, event.filename, event.lineno, event.colno]);
    });

    // Capture unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        queueLog('unhandled_rejection', [{
            message: 'Unhandled Promise Rejection',
            reason: event.reason ? String(event.reason) : 'Unknown'
        }]);
    });

    // Send remaining logs on page unload
    window.addEventListener('beforeunload', function() {
        if (logs.length > 0) {
            const data = JSON.stringify({ logs: logs });
            navigator.sendBeacon('{$endpoint}', data);
        }
    });
})();
</script>
JAVASCRIPT;
    }
}
