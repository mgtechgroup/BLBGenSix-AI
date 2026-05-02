<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SecurityHeadersService
{
    public function applyHeaders($response)
    {
        // HSTS - Force HTTPS for 2 years + include subdomains + preload
        $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');

        // Content Security Policy - Strict
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: blob: https: *.cloudfront.net",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://api.stripe.com https://*.blbgensixai.club wss://*.blbgensixai.club",
            "frame-src 'self' https://js.stripe.com https://*.stripe.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "manifest-src 'self'",
            "media-src 'self' blob: https: *.cloudfront.net",
            "object-src 'none'",
            "worker-src 'self' blob:",
            "upgrade-insecure-requests",
        ]));

        // Prevent framing
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Disable XSS filter (CSP handles this better)
        $response->headers->set('X-XSS-Protection', '0');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=(self "https://api.stripe.com")',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
        ]));

        // Cross-Origin policies
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Remove server signature
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Cache control for sensitive pages
        if ($this->isSensitivePage()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // Report-URI for CSP violations
        $response->headers->set('Report-To', json_encode([
            'group' => 'csp-endpoint',
            'max_age' => 86400,
            'endpoints' => [['url' => 'https://blbgensixai.club/api/v1/security/csp-report']],
        ]));

        return $response;
    }

    protected function isSensitivePage(): bool
    {
        $sensitivePatterns = [
            '/dashboard',
            '/profile',
            '/billing',
            '/api/',
            '/settings',
            '/verification',
        ];

        $path = Request::path();

        foreach ($sensitivePatterns as $pattern) {
            if (str_starts_with($path, ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }

    public function getCorsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => env('CORS_ALLOWED_ORIGIN', 'https://blbgensixai.club'),
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-CSRF-TOKEN, X-XSRF-TOKEN, X-Device-ID, X-Device-Canvas, X-Device-WebGL, X-Screen-Resolution, X-Timezone, X-Language, X-Platform, X-CPU-Cores, X-Memory',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '3600',
        ];
    }
}
