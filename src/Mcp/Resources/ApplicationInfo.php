<?php

declare(strict_types=1);

namespace Roots\Substrate\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Roots\Substrate\Mcp\ToolExecutor;
use Roots\Substrate\Mcp\Tools\Core\ApplicationInfo as ApplicationInfoTool;

class ApplicationInfo extends Resource
{
    public function __construct(protected ToolExecutor $toolExecutor)
    {
    }

    /**
     * The resource's description.
     */
    protected string $description = 'Comprehensive application information including PHP version, WordPress version, Acorn version, active theme, installed plugins, and Composer packages.';

    /**
     * The resource's URI.
     */
    protected string $uri = 'file://instructions/application-info.md';

    /**
     * The resource's MIME type.
     */
    protected string $mimeType = 'text/markdown';

    /**
     * Handle the resource request.
     */
    public function handle(): Response
    {
        $response = $this->toolExecutor->execute(ApplicationInfoTool::class);

        if ($response->isError()) {
            return $response;
        }

        $data = json_decode((string) $response->content(), true);

        if (! $data) {
            return Response::error('Error parsing application information');
        }

        return Response::json($data);
    }
}
