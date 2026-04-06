<?php

namespace App\Services;

use App\Models\BlockedEntity;
use App\Models\SecurityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SecurityEngine
{
    const RISK_THRESHOLD = 100; // Risk score that triggers sub-blocking
    const BLOCK_THRESHOLD = 500; // Risk score that triggers a hard block
    const CACHE_PREFIX = 'sec_risk_';

    /**
     * Log a security event and evaluate risk.
     */
    public static function logEvent(string $type, int $riskScore, ?string $description = null, ?array $payload = null)
    {
        $request = request();
        $ip = $request ? $request->ip() : '127.0.0.1';
        $path = $request ? $request->path() : 'console';
        $method = $request ? $request->method() : 'CLI';
        $ua = $request ? $request->userAgent() : 'CLI-Agent';
        $userId = null;
        try {
            if (Auth::check()) {
                $userId = Auth::id();
            }
        } catch (\Exception $e) {}

        // 0. Threat Intel & Geo (New in Phase 9)
        $geo = ($ip !== '127.0.0.1' && $ip !== '::1') ? \App\Services\ThreatIntelService::getGeoMetadata($ip) : ['country' => 'Local', 'city' => 'Local'];
        $reputation = ($ip !== '127.0.0.1' && $ip !== '::1') ? \App\Services\ThreatIntelService::checkIpReputation($ip) : 0;

        // 1. Persist to Database
        $log = SecurityLog::create([
            'user_id' => $userId,
            'event_type' => $type,
            'risk_score' => $riskScore,
            'ip_address' => $ip,
            'request_path' => $path,
            'request_method' => $method,
            'payload' => \App\Support\Security\DataMasker::mask($payload),
            'user_agent' => $ua,
            'description' => $description,
            'country' => $geo['country'],
            'city' => $geo['city'],
            'reputation_score' => $reputation,
            'session_id' => session()->getId(),
            'device_id' => md5($ua . $ip), // Simple fingerprint
        ]);

        // 2. Immediate Block for high-risk external reputation
        if ($reputation >= 90) {
            self::blockEntity($ip, 'ip', "Automated block: High-risk reputation score ({$reputation}) from AbuseIPDB");
        }

        // 3. Update Risk in Cache (Exempt Local)
        if ($ip !== '127.0.0.1' && $ip !== '::1' && $ip !== 'localhost') {
            self::incrementRisk($ip, $riskScore);
            if ($userId) {
                self::incrementRisk("user_{$userId}", $riskScore);
            }
        }

        // 4. Evaluate for Blocking
        self::evaluateThreat($ip, $userId);

        return $log;
    }

    /**
     * Increment risk score in cache (Ttl: 24 hours).
     */
    private static function incrementRisk(string $identifier, int $score)
    {
        $key = self::CACHE_PREFIX . $identifier;
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + $score, now()->addDay());
    }

    /**
     * Check if an entity is currently blocked.
     */
    public static function isBlocked(string $ip, $userId = null): bool
    {
        // Check hard blocks in DB
        $isIpBlocked = BlockedEntity::where('identifier', $ip)
            ->where('type', 'ip')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->exists();

        if ($isIpBlocked) return true;

        if ($userId) {
            $isUserBlocked = BlockedEntity::where('identifier', $userId)
                ->where('type', 'user')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->exists();

            if ($isUserBlocked) return true;
        }

        return false;
    }

    /**
     * Evaluate risk scores and trigger blocks if necessary.
     */
    private static function evaluateThreat(string $ip, $userId = null)
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) return;

        $ipRisk = (int) Cache::get(self::CACHE_PREFIX . $ip, 0);
        
        if ($ipRisk >= self::BLOCK_THRESHOLD) {
            self::blockEntity($ip, 'ip', "Automated block: Risk score {$ipRisk} exceeded threshold.");
        }

        if ($userId) {
            $userRisk = (int) Cache::get(self::CACHE_PREFIX . "user_{$userId}", 0);
            if ($userRisk >= self::BLOCK_THRESHOLD) {
                self::blockEntity($userId, 'user', "Automated block: Risk score {$userRisk} exceeded threshold.");
            }
        }
    }

    /**
     * Hard-block an entity in the database.
     */
    public static function blockEntity(string $identifier, string $type, ?string $reason = null, int $hours = 24)
    {
        BlockedEntity::updateOrCreate(
            ['identifier' => $identifier, 'type' => $type],
            [
                'reason' => $reason,
                'expires_at' => $hours > 0 ? now()->addHours($hours) : null,
            ]
        );

        Log::warning("Security Alert: Blocked {$type} [{$identifier}]. Reason: {$reason}");
    }
}
