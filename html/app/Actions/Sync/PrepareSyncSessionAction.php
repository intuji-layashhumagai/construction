<?php

namespace App\Actions\Sync;

use App\DTOs\SyncContext;
use App\DTOs\SyncSession;
use App\Enums\SyncDirection;

final class PrepareSyncSessionAction
{
    public static function handle(string $deviceId, string $workerId, array $syncData): SyncContext
    {
        // Initiate sync session
        $sessionId = InitiateSyncAction::handle($deviceId, SyncDirection::UPLOAD, $workerId);
        $syncContext = SyncContext::create($sessionId, $deviceId, $workerId);

        // Convert model to DTO for VectorClockAction
        $vectorClockState = $syncContext->sessionModel->vector_clock_state ?? [];
        if (is_string($vectorClockState)) {
            $vectorClockState = json_decode($vectorClockState, true) ?? [];
        }

        $syncSessionDto = new SyncSession([
            'sessionId' => $syncContext->sessionModel->id,
            'deviceId' => $syncContext->sessionModel->device_id,
            'direction' => $syncContext->sessionModel->direction,
            'status' => $syncContext->sessionModel->status,
            'vector_clock_state' => $vectorClockState,
            'bytesTransferred' => $syncContext->sessionModel->bytes_transferred ?? 0,
        ]);

        // Process vector clocks for causality and ordering
        $syncContext->processedEvents = ProcessVectorClocksAction::handle($syncData, $syncSessionDto);

        // Update model with merged vector clock state
        $syncContext->sessionModel->vector_clock_state = $syncSessionDto->currentVectorClock;
        $syncContext->sessionModel->save();

        return $syncContext;
    }
}
