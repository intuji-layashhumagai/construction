<?php

namespace App\Actions\Auth;

use App\Actions\Event\ProcessSingleEventAction;
use App\Models\Event;

final class GenerateVectorClock
{
    /**
     * Generate a vector clock for a worker's session based on their event history.
     *
     * @param  string  $workerId  The worker's ID
     * @param  string  $deviceId  The device ID performing the action
     * @return array Vector clock for this operation
     */
    public static function handle(string $workerId, string $deviceId): array
    {
        // Get the latest vector clock for this worker's session
        $latestEvent = Event::where('entity_type', 'worker_session')
            ->where('entity_id', $workerId)
            ->orderBy('server_created_at', 'desc')
            ->orderBy('sequence_number', 'desc')
            ->select('vector_clock')
            ->first();

        // Start with the latest vector clock or empty array for new workers
        $baseVectorClock = $latestEvent ? $latestEvent->vector_clock : [];

        // Increment this device's counter using the proven ProcessSingleEventAction method
        return ProcessSingleEventAction::incrementClock($baseVectorClock, $deviceId);
    }

    /**
     * Get the next sequence number for a worker's session events.
     *
     * @param  string  $workerId  The worker's ID
     * @return int Next sequence number
     */
    public static function getNextSequenceNumber(string $workerId): int
    {
        $latestSequence = Event::where('entity_type', 'worker_session')
            ->where('entity_id', $workerId)
            ->max('sequence_number') ?? 0;

        return $latestSequence + 1;
    }
}
