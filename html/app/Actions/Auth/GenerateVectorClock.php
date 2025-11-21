<?php

namespace App\Actions\Auth;

use App\Models\Event;

final class GenerateVectorClock
{
    /**
     * Generate a new vector clock for the system.
     *
     * This method creates a vector clock by taking the latest event's vector clock
     * and incrementing all server dimensions, then merging to preserve other device knowledge.
     */
    public static function handle(): array
    {
        // Get the list of cluster servers from configuration
        $clusterServerIds = config('project.servers', ['Server-A', 'Server-B', 'Server-C']);

        // Retrieve the vector clock from the most recent event
        $latestEvent = Event::orderBy('server_created_at', 'desc')
            ->orderBy('sequence_number', 'desc')
            ->select('vector_clock')
            ->first();

        // Start with the latest vector clock or an empty array if no events exist
        $latestVectorClock = $latestEvent ? $latestEvent->vector_clock : [];

        // Initialize the new vector clock with the latest values
        $initialVectorClock = $latestVectorClock;

        // Increment all server dimensions to reflect the current operation
        foreach ($clusterServerIds as $serverId) {
            $initialVectorClock[$serverId] = ($initialVectorClock[$serverId] ?? 0) + 1;
        }

        // Merge to ensure all device dimensions are saved with maximum values
        $initialVectorClock = self::merge($latestVectorClock, $initialVectorClock);

        return $initialVectorClock;
    }

    /**
     * Merge two vector clocks using the maximum value for each dimension.
     */
    private static function merge(array $vc1, array $vc2): array
    {
        $merged = [];
        $allDeviceIds = array_keys(array_merge($vc1, $vc2));

        foreach ($allDeviceIds as $deviceId) {
            $merged[$deviceId] = max($vc1[$deviceId] ?? 0, $vc2[$deviceId] ?? 0);
        }

        return $merged;
    }
}
