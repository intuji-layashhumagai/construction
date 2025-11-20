<?php

namespace App\Actions\Event;

use App\Models\Event;

/**
 * Save an event to the event store with the merged vector clock.
 *
 * This action creates the new authoritative clock by merging the current
 * authoritative clock with the device's clock, ensuring causal consistency.
 */
final class SaveEventToStoreAction
{
    public static function handle(array $eventData, array $mergedClock): Event
    {
        // Create the event record with all necessary data
        $event = new Event([
            'entity_type' => $eventData['entity_type'],
            'entity_id' => $eventData['entity_id'],
            'event_type' => $eventData['event_type'],
            'device_id' => $eventData['device_id'],
            'worker_id' => $eventData['worker_id'],
            'event_data' => $eventData['event_data'],
            'sequence_number' => $eventData['sequence_number'],
            'vector_clock' => $mergedClock, // Store the merged authoritative clock
            'server_created_at' => now(), // Explicitly set server timestamp
        ]);

        $event->save();

        return $event;
    }
}
