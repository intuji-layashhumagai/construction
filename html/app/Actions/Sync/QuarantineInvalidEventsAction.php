<?php

namespace App\Actions\Sync;

use App\DTOs\QuarantineContext;
use App\Models\QuarantinedEvent;
use Carbon\Carbon;

final class QuarantineInvalidEventsAction
{
    public static function handle(QuarantineContext $context): void
    {
        // Create a map of original events for quick lookup
        $processedEventMap = [];
        foreach ($context->processedEvents as $processedEvent) {
            // Use a unique identifier to match processed events back to invalid events
            $key = $processedEvent['id'] ?? json_encode($processedEvent);
            $processedEventMap[$key] = $processedEvent;
        }

        foreach ($context->invalidEvents as $index => $eventData) {
            // Find the specific violations for this event
            $eventViolations = array_filter($context->violations, function ($violation) use ($index) {
                return ($violation['event_index'] ?? null) === $index;
            });

            // Get the processed version of this event (with merged vector clocks)
            $eventKey = $eventData['id'] ?? json_encode($eventData);
            $processedEventData = $processedEventMap[$eventKey] ?? $eventData;

            QuarantinedEvent::create([
                'session_id' => $context->sessionId,
                'device_id' => $context->deviceId,
                'worker_id' => $context->workerId,
                'event_data' => $processedEventData, // Store processed event with merged vector clocks
                'validation_errors' => array_values($eventViolations),
                'vector_clock' => $processedEventData['merged_vector_clock'] ?? $processedEventData['vector_clock'] ?? null,
                'device_timestamp' => isset($eventData['timestamp']) ? Carbon::parse($eventData['timestamp']) : null,
                'server_received_at' => $context->serverReceivedAt,
                'status' => 'pending_review',
            ]);
        }
    }
}
