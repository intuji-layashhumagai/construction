<?php

namespace App\Services;

use App\Actions\Sync\GetSingleSessionAction;
use App\DTOs\SyncSession;
use App\Jobs\ProcessEventBatchJob;
use App\Services\Sync\SyncProtocol;
use Symfony\Component\HttpFoundation\Response;

class SyncService
{
    public function __construct(private readonly SyncProtocol $syncProtocol) {}

    /**
     * Process a batch of events from an offline device.
     *
     * This method handles the synchronization of multiple events, ensuring they are
     * integrated into the event store in the correct causal order while resolving
     * any conflicts that arise from concurrent modifications.
     */
    public function processEventBatch(array $eventsFromDevice): ?Response
    {
        if (empty($eventsFromDevice)) {
            return null;
        }

        $deviceId = $eventsFromDevice[0]['device_id'];

        ProcessEventBatchJob::dispatch($deviceId, $eventsFromDevice);

        return response()->json([
            'success' => 'Sync Started Successfully',
        ], 201);
    }

    /**
     * Process sync data using the new protocol.
     */
    public function processSyncData(string $sessionId, array $syncData): array
    {
        $session = GetSingleSessionAction::handle($sessionId);
        if (! $session) {
            throw new \Exception('Sync session not found');
        }

        $sessionData = SyncSession::fromArray($session->toArray());
        $results = $this->syncProtocol->processSyncData($sessionData, $syncData);

        return [
            'session_id' => $sessionId,
            'results' => $results,
            'status' => 'processed',
        ];
    }

    /**
     * Resume a sync session from a checkpoint.
     */
    public function resumeSync(string $checkpointId): array
    {
        $session = $this->syncProtocol->resumeSync($checkpointId);

        return [
            'session_id' => $session->id,
            'device_id' => $session->deviceId,
            'phase' => $session->phase->value,
            'checkpoint_id' => $checkpointId,
            'status' => 'resumed',
        ];
    }
}
