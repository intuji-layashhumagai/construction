<?php

namespace App\Actions\Event;

use App\Models\Event;

/**
 * Handle events that occurred concurrently on different devices.
 *
 * When two events happen at the same logical time (concurrent), we need a
 * deterministic way to decide which one takes precedence. We use a simple
 * rule: the device with the lexicographically smaller ID wins.
 */
final class HandleConcurrentEventsAction
{
    public static function handle(array $incomingEventData, ?Event $mostRecentEvent): ?Event
    {
        // For tie-breaking when events are concurrent, use lexicographical comparison of device IDs
        // The device with the smaller ID (lexicographically) takes precedence
        if (strcmp($incomingEventData['device_id'], $mostRecentEvent->device_id) < 0) {
            // Incoming device has priority - save the event
            $deviceClock = $incomingEventData['device_vector_clock'];
            $authoritativeClock = $mostRecentEvent->vector_clock;

            // Merge the clocks to create the new authoritative state
            $mergedClock = ProcessSingleEventAction::mergeVectorClocks($authoritativeClock, $deviceClock);

            return SaveEventToStoreAction::handle($incomingEventData, $mergedClock);
        }

        return null;

    }
}
