<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle( $request, Closure $next)
    {
        $response = $next($request);

        // Shto header-at e sigurisë
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=()');
        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            "default-src 'self'; script-src 'self' https://embed.tawk.to; frame-src https://meet.jit.si; img-src 'self' data:; connect-src 'self'; style-src 'self' 'unsafe-inline'; report-uri /csp-report"
        );

        return $response;
    }
}