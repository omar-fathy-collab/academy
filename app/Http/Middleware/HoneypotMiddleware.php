<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SecurityEngine;
use Symfony\Component\HttpFoundation\Response;

class HoneypotMiddleware
{
    /**
     * Handle an incoming request.
     * Bots and malicious actors often scan for common vulnerable paths.
     * Accessing these hidden routes will trigger an instant hard block.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $honeypots = [
            'wp-admin',
            'phpmyadmin',
            'config.php',
            '.env',
            'info.php',
            'setup-config.php',
            'administrator',
            'xmlrpc.php',
            'cgi-bin',
            '.git',
            '.ssh',
            'etc/passwd',
            'server-status',
            'wp-login.php',
            'sql.php',
        ];

        $path = $request->path();

        foreach ($honeypots as $trap) {
            if (str_contains(strtolower($path), $trap)) {
                SecurityEngine::logEvent('honeypot_triggered', 100, "Entity accessed a deception trap: {$path}");
                SecurityEngine::blockEntity($request->ip(), 'ip', "Honeypot TRAP triggered: Accessing {$path}");
                
                return response()->json([
                    'error' => 'Security Error',
                    'message' => 'Your activity has been flagged as malicious. Access denied.'
                ], 403);
            }
        }

        return $next($request);
    }
}
