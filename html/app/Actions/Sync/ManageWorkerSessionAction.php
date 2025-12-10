<?php

namespace App\Actions\Sync;

use App\DTOs\SyncItem;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

final class ManageWorkerSessionAction
{
    public static function handle(SyncItem $item): void
    {
        // Handle different types of worker session events
        switch ($item->type) {
            case EventType::HOURS_LOGGED->value:
                self::handleHoursLogged($item);
                break;
            case EventType::STATUS_UPDATED->value:
                self::handleStatusUpdate($item);
                break;
            case EventType::WORK_SESSION_STARTED->value:
            case EventType::WORK_SESSION_ENDED->value:
                self::handleSessionEvent($item);
                break;
            case EventType::DEVICE_HANDOVER->value:
                self::handleDeviceHandover($item);
                break;
            default:
                // Skip events that don't need session management
                break;
        }
    }

    private static function handleHoursLogged(SyncItem $item): void
    {
        Log::info('Processing worker session hours logging', [
            'entityId' => $item->entityId,
            'deviceId' => $item->deviceId,
            'hours' => $item->data['hours'] ?? null,
            'date' => $item->data['date'] ?? null,
        ]);

        // Find existing hours logged events for this worker/date
        $existingHours = Event::where('entity_id', $item->entityId)
            ->where('event_type', EventType::HOURS_LOGGED->value)
            ->whereJsonContains('event_data->date', $item->data['date'] ?? '')
            ->orderBy('server_created_at', 'desc')
            ->get();

        $currentHours = $item->data['hours'] ?? 0;
        $currentType = $item->data['type'] ?? 'regular';

        $merged = false;

        foreach ($existingHours as $existing) {
            $existingData = $existing->event_data;
            if (is_string($existingData)) {
                $existingData = json_decode($existingData, true);
            }

            $existingHours = $existingData['hours'] ?? 0;
            $existingType = $existingData['type'] ?? 'regular';

            // Check if this is the same type of hours (regular vs overtime)
            if ($existingType === $currentType) {
                // Merge hours from same type
                $totalHours = $existingHours + $currentHours;

                $mergedData = $existingData;
                $mergedData['hours'] = $totalHours;
                $mergedData['merged_from_devices'] = array_unique(array_merge(
                    $mergedData['merged_from_devices'] ?? [$existing->device_id],
                    [$item->deviceId]
                ));

                // Track merge operation for audit
                $mergedData['merge_operations'] = ($mergedData['merge_operations'] ?? 0) + 1;
                $mergedData['last_merge_timestamp'] = now()->toISOString();

                // Update the existing event
                $existing->event_data = $mergedData;
                $existing->save();

                Log::info('Merged worker session hours', [
                    'existing_event_id' => $existing->id,
                    'total_hours' => $totalHours,
                    'type' => $currentType,
                ]);

                $merged = true;
                break;
            }
        }

        if (! $merged) {
            Log::info('No merge performed for hours logged event', [
                'entityId' => $item->entityId,
                'reason' => 'No existing hours of same type found for date',
            ]);
        }
    }

    private static function handleStatusUpdate(SyncItem $item): void
    {
        $status = $item->data['status'] ?? null;

        if ($status === 'on_break') {
            self::handleBreakStatus($item);
        }
    }

    private static function handleBreakStatus(SyncItem $item): void
    {
        // Only handle events with break times
        if (! isset($item->data['break_start']) || ! isset($item->data['break_end'])) {
            return;
        }

        Log::info('Processing worker session break management', [
            'entityId' => $item->entityId,
            'deviceId' => $item->deviceId,
            'break_start' => $item->data['break_start'] ?? null,
            'break_end' => $item->data['break_end'] ?? null,
        ]);

        // Find existing break events for this worker/session
        $existingBreaks = Event::where('entity_id', $item->entityId)
            ->where('event_type', EventType::STATUS_UPDATED->value)
            ->whereJsonContains('event_data->status', 'on_break')
            ->orderBy('server_created_at', 'desc')
            ->get();

        $currentStart = strtotime($item->data['break_start'] ?? '00:00');
        $currentEnd = strtotime($item->data['break_end'] ?? '00:00');

        $merged = false;

        foreach ($existingBreaks as $existing) {
            $existingData = $existing->event_data;

            if (is_string($existingData)) {
                $existingData = json_decode($existingData, true);
            }

            $existingStart = strtotime($existingData['break_start'] ?? '00:00');
            $existingEnd = strtotime($existingData['break_end'] ?? '00:00');

            // Check for overlap or adjacency
            if (($currentStart <= $existingEnd && $currentEnd >= $existingStart) ||
                ($currentEnd == $existingStart) || ($currentStart == $existingEnd)) {

                // Merge the breaks
                $newStart = min($currentStart, $existingStart);
                $newEnd = max($currentEnd, $existingEnd);

                $mergedData = $existingData;
                $mergedData['break_start'] = date('H:i', $newStart);
                $mergedData['break_end'] = date('H:i', $newEnd);
                $mergedData['merged_from_devices'] = array_unique(array_merge(
                    $mergedData['merged_from_devices'] ?? [$existing->device_id],
                    [$item->deviceId]
                ));

                // Track merge operation for audit
                $mergedData['merge_operations'] = ($mergedData['merge_operations'] ?? 0) + 1;
                $mergedData['last_merge_timestamp'] = now()->toISOString();

                // Update the existing event
                $existing->event_data = $mergedData;
                $existing->save();

                Log::info('Merged worker session break', [
                    'existing_event_id' => $existing->id,
                    'new_break_start' => $mergedData['break_start'],
                    'new_break_end' => $mergedData['break_end'],
                ]);

                $merged = true;
                break;
            }
        }

        if (! $merged) {
            Log::info('No merge performed for worker session break event', [
                'entityId' => $item->entityId,
                'reason' => 'No overlapping or adjacent break times found',
            ]);
        }
    }

    private static function handleSessionEvent(SyncItem $item): void
    {
        Log::info('Processing worker session lifecycle event', [
            'entityId' => $item->entityId,
            'deviceId' => $item->deviceId,
            'event_type' => $item->type,
        ]);

        // For session start/end events, ensure continuity across devices
        // This helps maintain session integrity when workers switch devices

        if ($item->type === EventType::WORK_SESSION_STARTED->value) {
            // Check for existing active sessions
            $activeSession = Event::where('entity_id', $item->entityId)
                ->where('event_type', EventType::WORK_SESSION_STARTED->value)
                ->where('server_created_at', '>=', now()->subHours(12)) // Last 12 hours
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('events as e2')
                        ->whereRaw('e2.entity_id = events.entity_id')
                        ->where('e2.event_type', EventType::WORK_SESSION_ENDED->value)
                        ->where('e2.server_created_at', '>', 'events.server_created_at');
                })
                ->first();

            if ($activeSession) {
                Log::info('Active session found, linking devices', [
                    'existing_session_device' => $activeSession->device_id,
                    'new_device' => $item->deviceId,
                ]);
            }
        }
    }

    private static function handleDeviceHandover(SyncItem $item): void
    {
        Log::info('Processing device handover event', [
            'entityId' => $item->entityId,
            'deviceId' => $item->deviceId,
            'from_worker' => $item->data['from_worker_id'] ?? null,
            'to_worker' => $item->data['to_worker_id'] ?? null,
        ]);

        // Device handover events help track session transitions
        // This ensures proper isolation between workers using the same device

        $fromWorker = $item->data['from_worker_id'] ?? null;
        $toWorker = $item->data['to_worker_id'] ?? null;

        if ($fromWorker && $toWorker) {
            // Ensure previous worker's session is properly closed
            $previousSession = Event::where('entity_id', $fromWorker)
                ->where('event_type', EventType::WORK_SESSION_ENDED->value)
                ->where('device_id', $item->deviceId)
                ->where('server_created_at', '>=', now()->subHours(1))
                ->first();

            if (! $previousSession) {
                Log::warning('Device handover without proper session closure', [
                    'from_worker' => $fromWorker,
                    'device_id' => $item->deviceId,
                ]);
            }
        }
    }
}
