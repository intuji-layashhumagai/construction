<?php

namespace App\Actions\Sync;

use App\DTOs\SyncSession;
use App\Enums\NetworkQuality;
use App\Services\Sync\NetworkConditionDetector;

final class ScheduleSyncAction
{
    public static function handle(SyncSession $session): ScheduleResultAction
    {
        $networkQuality = self::assessQuality();
        $availableBandwidth = self::getCurrentBandwidth();
        $estimatedDuration = self::estimateSyncDuration($session, $availableBandwidth);

        // Immediate sync for good conditions and short duration
        if ($networkQuality === NetworkQuality::EXCELLENT && $estimatedDuration < 300) {
            return new ScheduleResultAction(immediate: true);
        }

        // Schedule for poor conditions or long syncs
        if ($networkQuality === NetworkQuality::POOR || $estimatedDuration > 3600) {
            $optimalTime = self::findOptimalSyncTime($session);

            return new ScheduleResultAction(
                scheduled: true,
                time: $optimalTime,
                reason: 'Poor network conditions or long sync duration',
                estimatedDuration: $estimatedDuration
            );
        }

        // Chunked sync for medium conditions
        return new ScheduleResultAction(
            chunked: true,
            chunkSize: self::calculateOptimalChunkSize($availableBandwidth),
            interval: self::calculateSyncInterval($networkQuality),
            estimatedDuration: $estimatedDuration
        );
    }

    private static function assessQuality(): NetworkQuality
    {
        $detector = new NetworkConditionDetector;

        return $detector->assessQuality();
    }

    private static function getCurrentBandwidth(): int
    {
        return MeasureBandwidthAction::getCurrentBandwidth();
    }

    private static function findOptimalSyncTime(): \DateTime
    {
        // Find next low-traffic period
        return self::predictLowTrafficTime();
    }

    private static function estimateSyncDuration(SyncSession $session, int $bandwidth): int
    {
        $estimatedSize = self::estimateCompressedSize($session);

        return (int) ($estimatedSize / $bandwidth);
    }

    private static function estimateCompressedSize(): int
    {
        // Rough estimation based on session data
        $baseSize = 1024 * 1024; // 1MB base estimate
        $compressionRatio = 0.3; // Assume 70% compression

        return (int) ($baseSize * $compressionRatio);
    }

    private static function calculateOptimalChunkSize(int $bandwidth): int
    {
        // Aim for chunks that take 30-60 seconds
        $targetDuration = 45; // seconds

        return $bandwidth * $targetDuration;
    }

    private static function calculateSyncInterval(NetworkQuality $quality): int
    {
        return match ($quality) {
            NetworkQuality::GOOD => 60,      // 1 minute between chunks
            NetworkQuality::FAIR => 300,     // 5 minutes between chunks
            NetworkQuality::POOR => 900,     // 15 minutes between chunks
            default => 1800,                 // 30 minutes for unknown
        };
    }

    private static function predictLowTrafficTime(): \DateTime
    {
        $now = now();
        $hour = $now->hour;

        // Assume low traffic between 2-5 AM
        if ($hour >= 2 && $hour <= 5) {
            return $now; // Already in low traffic time
        } elseif ($hour < 2) {
            return $now->setHour(2); // Tonight at 2 AM
        } else {
            return $now->addDay()->setHour(2); // Tomorrow at 2 AM
        }
    }
}
