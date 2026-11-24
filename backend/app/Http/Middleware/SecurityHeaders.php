<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('X-Robots-Tag', 'noindex, nofollow');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (! $request->is('docs/*')) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "img-src 'self' data: blob:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self'",
                "font-src 'self' data:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
            ]));
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
