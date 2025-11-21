<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;

final class TrackDeviceUsageAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(string $workerId, string $deviceId, string $certificateSerial): void
    {

        $maxDevicePerUser = config('project.auth.suspicious_activity_threshold');
        $cacheTTL = config('project.auth.cache_ttl');

        $key = "user_devices_{$workerId}";
        $devices = Cache::get($key, []);

        $currentTime = now()->toISOString();

        // Update or add device
        $devices[$deviceId] = [
            'first_seen' => $devices[$deviceId]['first_seen'] ?? $currentTime,
            'last_seen' => $currentTime,
            'certificate_serial' => $certificateSerial,
            'usage_count' => ($devices[$deviceId]['usage_count'] ?? 0) + 1,
        ];

        // Keeping only recent devices
        if (count($devices) > $maxDevicePerUser) {
            // Remove oldest devices
            uasort($devices, fn ($a, $b) => strtotime($b['last_seen']) <=> strtotime($a['last_seen']));
            $devices = array_slice($devices, 0, $maxDevicePerUser, true);
        }

        Cache::put($key, $devices, $cacheTTL);
    }
}
