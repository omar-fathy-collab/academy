<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        // Remove X-Powered-By if present
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        // Add common security headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Debug header to verify middleware execution
        $response->headers->set('X-Security-Policy-Active', 'true');

        // Content Security Policy - Allowing necessary CDNs and local Dev server for Vite
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: http://localhost:5173 http://127.0.0.1:5173 http://localhost:5174 http://127.0.0.1:5174 https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://www.googletagmanager.com https://www.gstatic.com; " .
               "script-src-elem 'self' 'unsafe-inline' blob: http://localhost:5173 http://127.0.0.1:5173 http://localhost:5174 http://127.0.0.1:5174 https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://www.googletagmanager.com https://www.gstatic.com; " .
               "style-src 'self' 'unsafe-inline' http://localhost:5173 http://127.0.0.1:5173 http://localhost:5174 http://127.0.0.1:5174 https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://unpkg.com https://*.fontawesome.com https://site-assets.fontawesome.com; " .
               "style-src-elem 'self' 'unsafe-inline' http://localhost:5173 http://127.0.0.1:5173 http://localhost:5174 http://127.0.0.1:5174 https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://unpkg.com https://*.fontawesome.com https://site-assets.fontawesome.com; " .
               "img-src 'self' data: blob: https: http:; " .
               "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com https://*.fontawesome.com; " .
               "connect-src 'self' blob: http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:5174 http://127.0.0.1:5174 ws://localhost:5174 ws://127.0.0.1:5174 https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdnjs.cloudflare.com https://*.fontawesome.com; " .
               "media-src 'self' blob:; " .
               "frame-src 'self'; " .
               "worker-src 'self' blob:; " .
               "object-src 'none';";
        $response->headers->set('Content-Security-Policy', $csp);

        // Cache Control for sensitive pages
        if ($request->isMethod('GET') && $request->route() && in_array($request->route()->getName(), ['profile', 'dashboard'])) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        // HSTS: enable when serving over HTTPS
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
