<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic security headers on all responses (web + API).
 *
 * CSP with 'unsafe-inline'/'unsafe-eval': Livewire and Alpine execute
 * inline scripts and parse expressions via new Function() — without these
 * directives the UI breaks. The goal is to block external sources (script/
 * connect/frame 'self' only), not fighting inline XSS (that is covered
 * by Blade escaping + the wire:click audit, C1).
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self'; "
            ."frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

        // HSTS only makes sense behind a TLS terminator; on http://localhost the browser ignores it
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
