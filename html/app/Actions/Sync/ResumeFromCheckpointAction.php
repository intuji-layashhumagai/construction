<?php

namespace App\Actions\Sync;

use App\DTOs\SyncSession;
use App\Enums\SyncPhase as EnumsSyncPhase;
use App\Services\Sync\CheckpointService;
use App\Services\Sync\SyncException;

final class ResumeFromCheckpointAction
{
    public static function handle(string $checkpointId): SyncSession
    {
        $checkpointService = new CheckpointService;
        $checkpoint = $checkpointService->load($checkpointId);
        if (! $checkpoint) {
            throw new SyncException("Checkpoint {$checkpointId} not found");
        }

        return new SyncSession([
            'lastCheckpoint' => $checkpoint,
            'phase' => EnumsSyncPhase::RESUME,
            'processedItems' => $checkpoint->processedItems,
            'bytesTransferred' => $checkpoint->bytesTransferred,
        ]);
    }
}
