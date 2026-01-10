<?php

declare(strict_types=1);

namespace Roots\Substrate\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Roots\Substrate\Services\BrowserLogger;

class InjectSubstrate
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only inject into HTML responses
        if (! $response instanceof Response) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }

        // Inject the script before </body>
        $script = BrowserLogger::getScript();

        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $script.'</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }
}
