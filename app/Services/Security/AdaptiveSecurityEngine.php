<?php

namespace App\Services\Security;

use App\Services\SecurityEngine;
use App\Models\SecurityLog;
use App\Models\AttackPattern;
use App\Models\RiskProfile;
use App\Models\BlockedEntity;
use Illuminate\Support\Facades\Cache;

class AdaptiveSecurityEngine extends SecurityEngine
{
    /**
     * Enhanced log event with adaptive intelligence.
     */
    public static function logEvent(string $type, int $riskScore, ?string $description = null, ?array $payload = null)
    {
        $ip = request()->ip();
        $userId = auth()->id();
        $profile = RiskProfile::getForIdentifier($ip);

        // 1. Check for hits against learned patterns
        $patternId = self::findMatchingPattern($payload);
        if ($patternId) {
            $pattern = AttackPattern::find($patternId);
            $riskScore += $pattern->confidence_score;
            $description .= " [Learned Pattern: {$pattern->name}]";
        }

        // 2. Adjust score based on Risk Profile (Trust)
        // If trust is high (100), reduce score. If low, increase.
        $trustFactor = (100 - $profile->trust_score) / 100;
        $riskScore *= (1 + $trustFactor);

        // 3. Global Threat Level scaling
        $threatLevel = self::getCurrentThreatLevel();
        if ($threatLevel > 70) {
            $riskScore *= 1.5; // Stricter during active waves
        }

        // 4. Persistence & Blocking
        $log = parent::logEvent($type, $riskScore, $description, $payload, $userId);
        
        if ($log) {
            $log->update([
                'pattern_id' => $patternId,
                'adaptive_weight' => $riskScore > 100 ? 5 : 1,
            ]);

            // Update Risk Profile
            $profile->increment('attack_count');
            $profile->decrement('trust_score', min($riskScore / 10, 20));
            $profile->update(['last_activity_at' => now()]);
        }

        return $log;
    }

    private static function findMatchingPattern($payload): ?int
    {
        if (empty($payload)) return null;

        $payloadStr = json_encode($payload);
        
        return Cache::remember('active_patterns', 60, function() use ($payloadStr) {
            return AttackPattern::where('is_active', true)
                ->where('confidence_score', '>', 40)
                ->get();
        })->first(function($pattern) use ($payloadStr) {
            return str_contains($payloadStr, $pattern->signature);
        })?->id;
    }

    public static function getCurrentThreatLevel(): int
    {
        return Cache::remember('global_threat_level', 300, function() {
            $recentAttacks = SecurityLog::where('created_at', '>', now()->subMinutes(30))
                ->where('risk_score', '>', 50)
                ->count();
            
            return min($recentAttacks * 5, 100);
        });
    }

    /**
     * Feedback Loop: Mark a log as a False Positive and restore trust.
     */
    public static function markFalsePositive(SecurityLog $log)
    {
        $log->update(['is_false_positive' => true]);
        
        $profile = RiskProfile::getForIdentifier($log->ip_address);
        $profile->increment('trust_score', 30); // Restore trust
        $profile->increment('false_positive_count');
        
        if ($profile->trust_score > 100) $profile->trust_score = 100;
        $profile->save();

        // If it was blocked, unblock it
        BlockedEntity::where('identifier', $log->ip_address)->delete();
    }
}
