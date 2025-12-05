<?php

namespace App\Actions\Sync;

use App\Actions\Event\ProcessSingleEventAction;
use App\DTOs\SyncSession;

final class ProcessVectorClocksAction
{
    /**
     * Process vector clocks for incoming events in a sync session.
     *
     * Merges device vector clocks with the session's current vector clock,
     * calculates causal ordering, and updates the session state.
     */
    public static function handle(array $events, SyncSession $session): array
    {
        $processedEvents = [];
        $maxClocks = $session->currentVectorClock;

        foreach ($events as $event) {
            // Merge vector clocks
            $eventClock = $event['device_vector_clock'] ?? [];
            $mergedClock = ProcessSingleEventAction::mergeVectorClocks($maxClocks, $eventClock);

            // Update max clocks seen so far
            $maxClocks = ProcessSingleEventAction::mergeVectorClocks($maxClocks, $mergedClock);

            // Add merged clock and causal order to event for storage
            $event['merged_vector_clock'] = $mergedClock;
            $event['causal_order'] = self::calculateCausalOrder($eventClock, $session->currentVectorClock);

            $processedEvents[] = $event;
        }

        // Update session's current vector clock
        $session->currentVectorClock = $maxClocks;

        return $processedEvents;
    }

    /**
     * Calculate causal ordering relationship between event and session clocks.
     * Returns: 'before', 'after', 'concurrent', 'same'
     */
    private static function calculateCausalOrder(array $eventClock, array $sessionClock): string
    {
        $comparison = ProcessSingleEventAction::compareVectorClocks($sessionClock, $eventClock);

        return match ($comparison) {
            'Happened-Before' => 'before',    // session happened before event
            'Happened-After' => 'after',      // session happened after event
            'Concurrent' => 'concurrent',     // events are concurrent
            default => 'same'                 // clocks are identical
        };
    }
}
