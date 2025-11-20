<?php

namespace App\Actions\Event;

use App\Models\Event;

/**
 * Process a single event from a device, handling causality and conflicts.
 *
 * This action encapsulates the logic for:
 * 1. Getting the current authoritative state for the entity
 * 2. Comparing vector clocks to determine event ordering
 * 3. Delegating to appropriate actions for persistence or conflict resolution
 */
final class ProcessSingleEventAction
{
    /**
     * Merge two vector clocks by taking the maximum value for each device.
     */
    public static function mergeVectorClocks(array $firstClock, array $secondClock): array
    {
        $resultClock = [];

        // Get all unique device IDs from both clocks
        $allDeviceIds = array_keys(array_merge($firstClock, $secondClock));

        foreach ($allDeviceIds as $deviceId) {
            $firstCount = $firstClock[$deviceId] ?? 0;
            $secondCount = $secondClock[$deviceId] ?? 0;

            // Take the maximum count for each device ID
            $maxCount = max($firstCount, $secondCount);

            // Compact: only include non-zero entries to prevent clock growth
            if ($maxCount > 0) {
                $resultClock[$deviceId] = $maxCount;
            }
        }

        return $resultClock;
    }

    /**
     * Compare two vector clocks to determine their causal relationship.
     */
    public static function compareVectorClocks(array $firstClock, array $secondClock): string
    {
        $firstIsLessOrEqual = true;
        $secondIsLessOrEqual = true;

        $deviceIds = array_keys(array_merge($firstClock, $secondClock));

        foreach ($deviceIds as $deviceId) {
            $firstCount = $firstClock[$deviceId] ?? 0;
            $secondCount = $secondClock[$deviceId] ?? 0;

            // Check if first clock is greater than second in any dimension
            if ($firstCount > $secondCount) {
                $firstIsLessOrEqual = false;
            }
            // Check if second clock is greater than first in any dimension
            if ($secondCount > $firstCount) {
                $secondIsLessOrEqual = false;
            }
        }

        if ($firstIsLessOrEqual && ! $secondIsLessOrEqual) {
            // Second clock happened after first (first is a predecessor)
            return 'Happened-Before';
        }

        if ($secondIsLessOrEqual && ! $firstIsLessOrEqual) {
            // First clock happened after second (second is a predecessor, this is a LATE event)
            return 'Happened-After';
        }

        // If neither strictly dominates the other, they are concurrent
        return 'Concurrent';
    }

    public static function handle(array $incomingEventData): ?Event
    {
        // todo: refactoring the vectorclock service to actions
        $entityId = $incomingEventData['entity_id'];
        $deviceClock = $incomingEventData['device_vector_clock'];

        // Find the most recent event for this entity to get the current authoritative clock
        $mostRecentEvent = Event::where('entity_id', $entityId)
            ->orderBy('server_created_at', 'desc')
            ->select('id', 'device_id', 'vector_clock')
            ->first();

        $authoritativeClock = $mostRecentEvent
            ? $mostRecentEvent->vector_clock
            : [];

        // Determine the causal relationship between the events
        $clockComparison = self::compareVectorClocks($authoritativeClock, $deviceClock);

        if ($clockComparison === 'Concurrent') {
            // Events happened independently, need conflict resolution
            $result = HandleConcurrentEventsAction::handle($incomingEventData, $mostRecentEvent);
            if (! $result) {
                // Conflict resolved by not saving the incoming event
                return null;
            }
        } else {
            // Events have a clear causal order - persist normally
            $mergedClock = self::mergeVectorClocks($authoritativeClock, $deviceClock);
            $result = SaveEventToStoreAction::handle($incomingEventData, $mergedClock);
        }

        return $result;
    }
}
