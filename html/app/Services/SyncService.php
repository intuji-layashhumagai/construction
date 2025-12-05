<?php

namespace App\Services;

use App\Actions\Sync\GetSingleSessionAction;
use App\Actions\Sync\ScheduleSyncAction;
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
     * Resume a sync session from a checkpoint and process sync data.
     */
    public function resumeSync(string $checkpointId, array $syncData): array
    {
        $session = $this->syncProtocol->resumeSync($checkpointId);
        $results = $this->syncProtocol->processSyncData($session, $syncData);

        return [
            'session_id' => $session->id,
            'results' => $results,
            'status' => 'resumed',
        ];
    }

    /**
     * Schedule a sync session for later processing based on network conditions.
     */
    public function scheduleSync(string $sessionId, array $syncData): array
    {
        $session = GetSingleSessionAction::handle($sessionId);
        if (! $session) {
            throw new \Exception('Sync session not found');
        }

        $sessionData = SyncSession::fromArray($session->toArray());
        $scheduleResult = ScheduleSyncAction::handle($sessionData);
        $decision = [
            'type' => 'immediate',
            'reason' => 'Good network conditions',
        ];

        if ($scheduleResult->scheduled) {
            $decision = [
                'type' => 'scheduled',
                'scheduled_time' => $scheduleResult->time?->format('c'),
                'reason' => $scheduleResult->reason,
                'estimated_duration' => $scheduleResult->estimatedDuration,
            ];
        } elseif ($scheduleResult->chunked) {
            $decision = [
                'type' => 'chunked',
                'chunk_size' => $scheduleResult->chunkSize,
                'interval' => $scheduleResult->interval,
                'estimated_duration' => $scheduleResult->estimatedDuration,
            ];
        }

        return [
            'session_id' => $sessionId,
            'schedule_decision' => $decision,
            'sync_data_count' => count($syncData),
        ];
    }
}
