<?php

declare(strict_types=1);

namespace Roots\Substrate\Concerns;

trait ReadsLogs
{
    /**
     * Get the timestamp regex pattern for PSR-3 logs.
     */
    protected function getTimestampRegex(): string
    {
        return '\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\\]';
    }

    /**
     * Get the entry split regex pattern.
     */
    protected function getEntrySplitRegex(): string
    {
        return '/(?='.$this->getTimestampRegex().')/';
    }

    /**
     * Get the error entry regex pattern.
     */
    protected function getErrorEntryRegex(): string
    {
        return '/^'.$this->getTimestampRegex().'.*\\.ERROR:/';
    }

    /**
     * Get the starting chunk size for log reading.
     */
    protected function getChunkSizeStart(): int
    {
        return 64 * 1024; // 64 KB
    }

    /**
     * Get the maximum chunk size for log reading.
     */
    protected function getChunkSizeMax(): int
    {
        return 1024 * 1024; // 1 MB
    }

    /**
     * Resolve the current log file path.
     *
     * For WordPress, this is typically WP_CONTENT_DIR/debug.log
     * For Acorn, this can be storage/logs/laravel.log
     */
    protected function resolveLogFilePath(): string
    {
        // First try Acorn's log path
        $acornLogPath = storage_path('logs/laravel.log');
        if (file_exists($acornLogPath)) {
            return $acornLogPath;
        }

        // Check for daily log files
        $dailyLogPath = storage_path('logs/laravel-'.date('Y-m-d').'.log');
        if (file_exists($dailyLogPath)) {
            return $dailyLogPath;
        }

        // Try WordPress debug.log
        if (defined('WP_CONTENT_DIR')) {
            $wpDebugLog = WP_CONTENT_DIR.'/debug.log';
            if (file_exists($wpDebugLog)) {
                return $wpDebugLog;
            }
        }

        // Fallback - try to find any recent log file
        $logDir = storage_path('logs');
        if (is_dir($logDir)) {
            $files = glob($logDir.'/*.log');
            if ($files) {
                // Return the most recently modified
                usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

                return $files[0];
            }
        }

        // Default fallback
        return $acornLogPath;
    }

    /**
     * Determine if the given line is an ERROR log entry.
     */
    protected function isErrorEntry(string $line): bool
    {
        // Check for PSR-3 style errors
        if (preg_match($this->getErrorEntryRegex(), $line) === 1) {
            return true;
        }

        // Check for WordPress PHP errors
        if (preg_match('/PHP (Fatal error|Parse error|Warning|Notice|Error)/i', $line) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Read the last N log entries from a log file.
     *
     * @return string[]
     */
    protected function readLastLogEntries(string $logFile, int $count): array
    {
        $chunkSize = $this->getChunkSizeStart();

        do {
            $entries = $this->scanLogChunkForEntries($logFile, $chunkSize);

            if (count($entries) >= $count || $chunkSize >= $this->getChunkSizeMax()) {
                break;
            }

            $chunkSize *= 2;
        } while (true);

        return array_slice($entries, -$count);
    }

    /**
     * Read the last ERROR entry from a log file.
     */
    protected function readLastErrorEntry(string $logFile): ?string
    {
        $chunkSize = $this->getChunkSizeStart();

        do {
            $entries = $this->scanLogChunkForEntries($logFile, $chunkSize);

            for ($i = count($entries) - 1; $i >= 0; $i--) {
                if ($this->isErrorEntry($entries[$i])) {
                    return trim((string) $entries[$i]);
                }
            }

            if ($chunkSize >= $this->getChunkSizeMax()) {
                return null;
            }

            $chunkSize *= 2;
        } while (true);
    }

    /**
     * Scan the last chunk of a log file for entries.
     *
     * @return string[]
     */
    protected function scanLogChunkForEntries(string $logFile, int $chunkSize): array
    {
        $fileSize = filesize($logFile);
        if ($fileSize === false) {
            return [];
        }

        $handle = fopen($logFile, 'r');
        if (! $handle) {
            return [];
        }

        try {
            $offset = max($fileSize - $chunkSize, 0);
            fseek($handle, $offset);

            // If we started mid-line, discard the partial line
            if ($offset > 0) {
                fgets($handle);
            }

            $content = stream_get_contents($handle);

            // Split by PSR-3 timestamp pattern
            $entries = preg_split($this->getEntrySplitRegex(), $content, -1, PREG_SPLIT_NO_EMPTY);
            if (! $entries) {
                // Try splitting by newlines for WordPress-style logs
                $entries = array_filter(explode("\n", $content), fn ($line) => ! empty(trim($line)));
            }

            return $entries ?: [];
        } finally {
            fclose($handle);
        }
    }
}
