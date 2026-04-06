<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedEntity;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityDashboardController extends Controller
{
    /**
     * Display the security overview.
     */
    public function index()
    {
        $stats = [
            'total_logs' => SecurityLog::count(),
            'total_blocks' => BlockedEntity::count(),
            'recent_threats' => SecurityLog::where('risk_score', '>=', 50)->where('created_at', '>', now()->subHours(24))->count(),
            'high_risk_ips' => SecurityLog::where('reputation_score', '>=', 80)->distinct('ip_address')->count('ip_address'),
            'anomalies' => SecurityLog::whereNotNull('anomaly_hint')->count(),
            'threat_level' => \App\Services\Security\AdaptiveSecurityEngine::getCurrentThreatLevel(),
        ];

        $recentLogs = SecurityLog::with(['user', 'pattern'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $activeBlocks = BlockedEntity::orderBy('created_at', 'desc')->get();
        $learnedPatterns = \App\Models\AttackPattern::orderBy('confidence_score', 'desc')->take(10)->get();
        $riskProfiles = \App\Models\RiskProfile::orderBy('trust_score', 'asc')->take(10)->get();

        return view('admin.security.dashboard', [
            'stats' => $stats,
            'recentLogs' => $recentLogs,
            'activeBlocks' => $activeBlocks,
            'learnedPatterns' => $learnedPatterns,
            'riskProfiles' => $riskProfiles,
            'config' => [
                'threat_intel_enabled' => !empty(config('services.abuseipdb.key')),
            ]
        ]);
    }

    /**
     * Mark a log as a False Positive (Human Feedback Loop).
     */
    public function markFalsePositive($id)
    {
        $log = SecurityLog::findOrFail($id);
        \App\Services\Security\AdaptiveSecurityEngine::markFalsePositive($log);

        return back()->with('success', 'Event marked as False Positive. IP trust restored.');
    }

    /**
     * Force logout for all active sessions (Extreme Response).
     */
    public function forceLogoutAll()
    {
        \Illuminate\Support\Facades\DB::table('sessions')->truncate();
        
        SecurityLog::create([
            'event_type' => 'incident_response',
            'risk_score' => 0,
            'ip_address' => request()->ip(),
            'description' => 'Admin initiated GLOBAL FORCE LOGOUT (Incident Response)',
        ]);

        return back()->with('success', 'Global session purge initiated.');
    }

    /**
     * Unblock an entity.
     */
    public function unblock(Request $request, $id)
    {
        $block = BlockedEntity::findOrFail($id);
        
        // Clear cache risk score for this identifier
        Cache::forget('sec_risk_' . $block->identifier);
        Cache::forget('sec_throttle_' . $block->identifier);

        $block->delete();

        return back()->with('success', 'Entity has been unblocked.');
    }

    /**
     * Clear all security logs.
     */
    public function clearLogs()
    {
        SecurityLog::truncate();
        return back()->with('success', 'Security logs have been cleared.');
    }
}
