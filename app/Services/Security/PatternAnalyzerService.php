<?php

namespace App\Services\Security;

use App\Models\SecurityLog;
use App\Models\AttackPattern;
use Illuminate\Support\Facades\Log;

class PatternAnalyzerService
{
    /**
     * Analyze recent logs to find recurring attack patterns.
     */
    public static function analyze()
    {
        // 1. Cluster by repeated payload fragments (excluding common noise)
        self::analyzePayloadPatterns();

        // 2. Cluster by suspicious path sequences
        self::analyzePathSequences();
    }

    private static function analyzePayloadPatterns()
    {
        $recentLogs = SecurityLog::where('created_at', '>=', now()->subHours(24))
            ->whereNotNull('payload')
            ->where('is_false_positive', false)
            ->get();

        $signatures = [];

        foreach ($recentLogs as $log) {
            $payload = json_encode($log->payload);
            
            // Simplified: detect common exploit strings if not already caught
            $patterns = [
                'sql_inject' => ["' OR 1=1", "--", "UNION SELECT"],
                'xss' => ["<script", "javascript:", "onload="],
                'path_traversal' => ["../", "/etc/passwd", "C:\\"],
            ];

            foreach ($patterns as $name => $markers) {
                foreach ($markers as $marker) {
                    if (str_contains($payload, $marker)) {
                        self::recordHit($name . "_" . md5($marker), $name, $marker);
                    }
                }
            }
        }
    }

    private static function recordHit(string $key, string $type, string $signature)
    {
        $pattern = AttackPattern::firstOrCreate(
            ['name' => $key],
            [
                'type' => $type,
                'signature' => $signature,
                'confidence_score' => 50,
            ]
        );

        $pattern->increment('hit_count');
        $pattern->update(['last_seen_at' => now()]);
        
        // Auto-escalate confidence if seen many times
        if ($pattern->hit_count > 50 && $pattern->confidence_score < 90) {
            $pattern->increment('confidence_score', 5);
        }
    }

    private static function analyzePathSequences()
    {
        // Placeholder for advanced sequence analysis
        // E.g., detecting IP hitting /login, then /admin, then /settings in < 1s
    }
}
