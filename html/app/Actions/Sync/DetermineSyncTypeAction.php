<?php

namespace App\Actions\Sync;

use App\Enums\SyncType;
use App\Models\SyncSession;

final class DetermineSyncTypeAction
{
    /**
     * Determine sync type based on device state and sync data.
     *
     * Checks for interrupted sessions with checkpoints or explicit resume indicators
     * in the sync data to decide between NEW and RESUME sync types.
     */
    public static function handle(string $deviceId, array $syncData): SyncType
    {
        // Check for resumable interrupted session
        if (self::hasResumableSession($deviceId)) {
            return SyncType::RESUME;
        }

        // Check for resume indicators in sync data
        if (self::hasResumeIndicators($syncData)) {
            return SyncType::RESUME;
        }

        return SyncType::NEW;
    }

    /**
     * Check if device has an interrupted session with valid checkpoint data.
     */
    private static function hasResumableSession(string $deviceId): bool
    {
        $session = SyncSession::where('device_id', $deviceId)
            ->where('status', 'interrupted')
            ->orderBy('last_activity_time', 'desc')
            ->first();

        return $session && !empty($session->last_checkpoint);
    }

    /**
     * Check if sync data contains resume indicators.
     */
    private static function hasResumeIndicators(array $syncData): bool
    {
        foreach ($syncData as $event) {
            if ((isset($event['resume_indicator']) && $event['resume_indicator'] === true) ||
                isset($event['checkpoint_id'])) {
                return true;
            }
        }

        return false;
    }
}
