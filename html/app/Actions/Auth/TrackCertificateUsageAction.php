<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;

final class TrackCertificateUsageAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(string $certificateSerial, string $deviceId, ?string $ipAddress = null)
    {

        $key = "cert_usage_{$certificateSerial}";
        $usageData = Cache::get($key, []);

        $currentTime = now()->toISOString();

        // Add current usage
        $usageData[] = [
            'device_id' => $deviceId,
            'ip_address' => $ipAddress,
            'timestamp' => $currentTime,
        ];

        // Only keeping recent usage (last 24 hours)
        $usageData = array_filter($usageData, function ($usage) {
            return now()->diffInHours($usage['timestamp']) < 24;
        });

        Cache::put($key, array_values($usageData), 86400); // 24 hours
    }
}
