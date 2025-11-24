<?php

namespace App\Services\Sync;

use App\DTOs\SyncSession as DTOsSyncSession;
use App\Enums\SyncDirection;
use App\Enums\SyncPhase;
use App\Models\SyncSession;
use Illuminate\Support\Facades\Log;

class SyncProtocol
{
    public function __construct() {}

    public function initiateSync(
        string $deviceId,
        SyncDirection $direction,
        string $workerId
    ): string {
        $session = new DTOsSyncSession([
            'deviceId' => $deviceId,
            'direction' => $direction,
            'phase' => SyncPhase::HANDSHAKE,
        ]);
        Log::info('Sync session initiated', [
            'sessionId' => $session->sessionId,
            'deviceId' => $deviceId,
            'direction' => $direction->value,
        ]);

        $storedSession = SyncSession::create([
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'direction' => $direction->value,
            'status' => $session->phase->value,
            'vector_clock_state' => json_encode($session->currentVectorClock ?? []),
            'last_checkpoint' => null,
            'bytes_transferred' => 0,
            'start_time' => now(),
            'last_activity_time' => now(),
        ]);

        return $storedSession->id;

    }
}
