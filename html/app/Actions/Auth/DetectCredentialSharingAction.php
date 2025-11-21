<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;

final class DetectCredentialSharingAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(int $certificateSerial)
    {
        $maxConcurrentDevice = config('project.auth.max_concurrent_device');

        $suspiciousActivityThreshold = config('project.auth.suspicious_activity_threshold');
        $suspiciousActivityThresholdTime = config('project.auth.suspicious_activity_threshold_time');

        $usageData = Cache::get("cert_usage_{$certificateSerial}", []);

        $result = [
            'suspicious_activity' => false,
            'risk_level' => 'low',
            'issues' => [],
            'recommendations' => [],
        ];

        if (empty($usageData)) {
            return $result;
        }

        $deviceCount = count(array_unique(array_column($usageData, 'device_id')));
        $ipCount = count(array_unique(array_column($usageData, 'ip_address')));

        // Check concurrent device usage
        if ($deviceCount > $maxConcurrentDevice) {
            $result['suspicious_activity'] = true;
            $result['issues'][] = "Certificate used on {$deviceCount} devices simultaneously (max allowed: ".$maxConcurrentDevice.')';
            $result['recommendations'][] = 'Consider revoking certificate due to potential sharing';
        }

        // Check for rapid device switching
        $timestamps = array_column($usageData, 'timestamp');
        sort($timestamps);

        $rapidSwitches = 0;
        for ($i = 1; $i < count($timestamps); $i++) {
            $timeDiff = strtotime($timestamps[$i]) - strtotime($timestamps[$i - 1]);
            if ($timeDiff < $suspiciousActivityThresholdTime * 60) { // Less than 5 minutes
                $rapidSwitches++;
            }
        }

        if ($rapidSwitches > $suspiciousActivityThreshold) {
            $result['suspicious_activity'] = true;
            $result['issues'][] = "Detected {$rapidSwitches} rapid device switches";
            $result['recommendations'][] = 'Monitor for credential sharing';
        }

        // Determine risk level
        if ($result['suspicious_activity']) {
            $issueCount = count($result['issues']);
            if ($issueCount >= 2) {
                $result['risk_level'] = 'high';
            } else {
                $result['risk_level'] = 'medium';
            }
        }

        return $result;
    }
}
