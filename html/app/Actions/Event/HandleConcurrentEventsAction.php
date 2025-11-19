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
    public static function handle(array $incomingEventData, ?Event $mostRecentEvent): void
    {

        // Get the authoritative clock from the most recent event
        $deviceClock = $incomingEventData['device_vector_clock'];
        $authoritativeClock = $mostRecentEvent->vector_clock;

        // Merge the clocks to create the new authoritative state
        $mergedClock = ProcessSingleEventAction::mergeVectorClocks($authoritativeClock, $deviceClock);

        SaveEventToStoreAction::handle($incomingEventData, $mergedClock);
    }
}
