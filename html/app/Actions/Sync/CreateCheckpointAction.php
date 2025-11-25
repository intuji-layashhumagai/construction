<?php

namespace App\Actions\Sync;

use App\DTOs\Checkpoint;
use App\DTOs\SyncSession;

final class CreateCheckpointAction
{
    public static function handle(SyncSession $session): Checkpoint
    {
        return new Checkpoint([
            'checkpointId' => "cp_{$session->id}_{$session->phase->value}_{$session->sequenceNumber}",
            'sequenceNumber' => $session->sequenceNumber,
            'processedItems' => $session->processedItems,
            'vectorClockState' => $session->currentVectorClock,
            'bytesTransferred' => $session->bytesTransferred,
            'timestamp' => now(),
        ]);
    }
}
