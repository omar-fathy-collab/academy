<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThreatIntelService
{
    protected static $abuseIpDbKey;

    /**
     * Check IP reputation via AbuseIPDB.
     */
    public static function checkIpReputation(string $ip): int
    {
        $key = config('services.abuseipdb.key');
        if (!$key) return 0;

        return Cache::remember("ip_rep_{$ip}", 3600, function () use ($ip, $key) {
            try {
                $response = Http::withHeaders([
                    'Key' => $key,
                    'Accept' => 'application/json'
                ])->get('https://api.abuseipdb.com/api/v2/check', [
                    'ipAddress' => $ip,
                    'maxAgeInDays' => 90
                ]);

                if ($response->successful()) {
                    return $response->json()['data']['abuseConfidenceScore'] ?? 0;
                }
            } catch (\Exception $e) {
                Log::warning("ThreatIntelService: Failed to check IP {$ip}: " . $e->getMessage());
            }

            return 0;
        });
    }

    /**
     * Get Geo metadata for an IP (Mocked or using a free provider like ip-api.com).
     */
    public static function getGeoMetadata(string $ip): array
    {
        return Cache::remember("ip_geo_{$ip}", 86400, function () use ($ip) {
            try {
                $response = Http::get("http://ip-api.com/json/{$ip}?fields=status,message,country,city");
                if ($response->successful() && $response->json()['status'] === 'success') {
                    return [
                        'country' => $response->json()['country'] ?? 'Unknown',
                        'city' => $response->json()['city'] ?? 'Unknown',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("ThreatIntelService: Failed to get Geo for {$ip}");
            }

            return ['country' => 'Unknown', 'city' => 'Unknown'];
        });
    }
}
