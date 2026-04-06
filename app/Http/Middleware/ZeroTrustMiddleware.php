<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\SecurityEngine;
use Symfony\Component\HttpFoundation\Response;

class ZeroTrustMiddleware
{
    /**
     * Handle an incoming request.
     * ZERO TRUST: Never trust a session alone. 
     * Re-verify session binding to IP and UA to prevent hijacking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the request has a valid signature (e.g. secure video stream or heartbeat), 
        // we trust the signed identity and bypass the session-binding checks.
        if ($request->hasValidSignature()) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $currentIp = $request->ip();
            $currentUa = $request->userAgent();
            $sessionId = Session::getId();

            // 1. Check for Session Hijacking (IP/UA change mid-session)
            $storedIp = Session::get('sec_bind_ip');
            $storedUa = Session::get('sec_bind_ua');

            if (!$storedIp) {
                // Initial session binding
                Session::put('sec_bind_ip', $currentIp);
                Session::put('sec_bind_ua', $currentUa);
            } else {
                if ($storedIp !== $currentIp || $storedUa !== $currentUa) {
                    // Skip if local environment and only IP changed (common with localhost vs 127.0.0.1)
                    if (app()->environment('local') && $storedUa === $currentUa) {
                        // Just update the IP binding and continue
                        Session::put('sec_bind_ip', $currentIp);
                        return $next($request);
                    }

                    SecurityEngine::logEvent('session_hijack_attempt', 80, "Session binding mismatch detected. Stored IP: {$storedIp}, Current IP: {$currentIp}");
                    
                    Auth::logout();
                    Session::flush();
                    
                    if ($request->header('X-Inertia')) {
                        return redirect()->route('login')->with([
                            'error' => 'Security Mismatch',
                            'message' => 'تم إنهاء الجلسة لأسباب أمنية (تغيير في المتصفح أو الشبكة).'
                        ]);
                    }

                    return response()->json([
                        'error' => 'Security Mismatch',
                        'message' => 'Your session has been terminated due to a security violation (Session Mismatch).'
                    ], 403);
                }
            }

            // 2. Behavioral Check: Unusual access volume
            $requestCount = (int) Session::get('sec_request_count', 0);
            Session::put('sec_request_count', $requestCount + 1);

            if ($requestCount > 500) { // Unusual number of requests in a single session period
                SecurityEngine::logEvent('unusual_activity_spike', 20, "User {$user->id} exceeded normal session request volume.");
            }
        }

        return $next($request);
    }
}
