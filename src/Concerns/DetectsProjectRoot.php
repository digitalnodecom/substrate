<?php

declare(strict_types=1);

namespace Roots\Substrate\Concerns;

trait DetectsProjectRoot
{
    /**
     * Get the project root directory (Bedrock root).
     */
    protected function projectRoot(): string
    {
        static $root = null;

        if ($root !== null) {
            return $root;
        }

        // Walk up from theme directory looking for Bedrock indicators
        $path = base_path();
        while ($path !== dirname($path)) {
            if (file_exists($path.'/wp-cli.yml') || file_exists($path.'/config/application.php')) {
                $root = $path;

                return $root;
            }
            $path = dirname($path);
        }

        // Fallback to base_path if no Bedrock root found
        $root = base_path();

        return $root;
    }
}
