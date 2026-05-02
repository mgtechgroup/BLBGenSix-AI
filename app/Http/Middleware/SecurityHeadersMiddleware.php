<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SecurityHeadersService;

class SecurityHeadersMiddleware
{
    protected SecurityHeadersService $headersService;

    public function __construct(SecurityHeadersService $headersService)
    {
        $this->headersService = $headersService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only apply to HTML/JSON responses (not file downloads)
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'html') || str_contains($contentType, 'json')) {
            $this->headersService->applyHeaders($response);
        }

        // Remove all revealing headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->remove('X-AspNet-Version');
        $response->headers->remove('X-AspNetMvc-Version');

        // Add cache-busting for security
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        return $response;
    }
}
