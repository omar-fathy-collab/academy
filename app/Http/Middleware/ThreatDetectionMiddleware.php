<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Security\AdaptiveSecurityEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThreatDetectionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userId = auth()->id();
        $user = auth()->user();

        // 0. Exemptions for Local Development and Full Admins
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || ($user && array_search($ip, ['127.0.0.1', '::1', 'localhost']) !== false) || ($user && method_exists($user, 'isAdminFull') && $user->isAdminFull())) {
            return $next($request);
        }

        // 1. Probing Detection (Predictive)
        $this->detectProbing($ip);

        // 2. Check if the entity is blocked
        if (AdaptiveSecurityEngine::isBlocked($ip, $userId)) {
            AdaptiveSecurityEngine::logEvent('blocked_access_attempt', 10, "Blocked entity [{$ip}] attempted access.");
            return response()->json([
                'error' => 'Your access has been temporarily suspended due to security concerns.',
                'code' => 'ACC_BLOCKED'
            ], 403);
        }

        // 2. IDOR / Sequential ID detection
        $this->detectSequentialIds($request);

        // 3. Suspicious Payload Detection (Heuristics)
        $this->detectSuspiciousPayload($request);

        // 4. Rate Limit escalation
        $riskKey = 'sec_risk_' . $ip;
        $riskScore = (int) Cache::get($riskKey, 0);
        if ($riskScore > AdaptiveSecurityEngine::RISK_THRESHOLD) {
             $this->enforceStrictThrottling($ip);
        }

        return $next($request);
    }

    /**
     * Detect if a user is trying to iterate over IDs (potential IDOR / Scraping).
     */
    private function detectSequentialIds(Request $request)
    {
        $params = $request->route() ? $request->route()->parameters() : [];
        foreach ($params as $key => $value) {
            if (is_numeric($value)) {
                $sessionId = session()->getId();
                $cacheKey = "seq_id_{$sessionId}_{$request->path()}";
                $lastId = Cache::get($cacheKey);

                if ($lastId && abs($value - $lastId) === 1) {
                    $occurrenceKey = "seq_id_count_{$sessionId}";
                    $count = Cache::increment($occurrenceKey);
                    
                    if ($count > 5) {
                        AdaptiveSecurityEngine::logEvent('idor_pattern_detected', 50, "Sequential ID access detected on path [{$request->path()}] for ID: {$value}");
                    }
                }
                Cache::put($cacheKey, $value, now()->addMinutes(10));
            }
        }
    }

    /**
     * Scan request for obvious attack patterns.
     */
    private function detectSuspiciousPayload(Request $request)
    {
        $patterns = [
            'union select' => 80,
            'sleep(' => 90,
            '<script' => 50,
            '../' => 40,
            '/etc/passwd' => 100,
            'exec(' => 100,
        ];

        $input = json_encode($request->all());
        
        foreach ($patterns as $pattern => $score) {
            if (stripos($input, $pattern) !== false) {
                AdaptiveSecurityEngine::logEvent('suspicious_payload', $score, "Attack signature detected: '{$pattern}'", $request->all());
            }
        }
    }

    /**
     * Predictive probing detection.
     */
    private function detectProbing(string $ip)
    {
        $key = "probing_count:{$ip}";
        $count = (int)Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(5));

        if ($count > 20) {
            AdaptiveSecurityEngine::logEvent(
                'predictive_probing', 
                40, 
                'Suspicious probing behavior (high request volume to distinct paths)',
                ['request_count' => $count]
            );
        }
    }

    /**
     * Enforce stricter throttling using cache instead of middleware to keep it dynamic.
     */
    private function enforceStrictThrottling(string $ip)
    {
        $throttleKey = "sec_throttle_{$ip}";
        $hits = (int) Cache::get($throttleKey, 0);
        
        if ($hits > 10) {
            abort(429, 'Too many requests. Risk level elevated.');
        }

        Cache::put($throttleKey, $hits + 1, now()->addMinute());
    }
}
