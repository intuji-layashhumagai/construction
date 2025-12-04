<?php

namespace App\Http\Controllers;

use App\Actions\Sync\DetermineSyncTypeAction;
use App\Actions\Sync\DispatchSyncJobAction;
use App\Actions\Sync\PrepareSyncSessionAction;
use App\Actions\Sync\ProcessAndValidateEventsAction;
use App\Enums\SyncType;
use App\Http\Requests\SyncEventProtocolRequest;
use App\Services\Sync\AutoSyncService;
use App\Services\SyncResponseBuilder;
use App\Services\SyncService;

class SyncController extends Controller
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly AutoSyncService $autoSyncService,
        private readonly ProcessAndValidateEventsAction $processAndValidateEventsAction
    ) {}

    /**
     * Single intelligent sync endpoint that handles everything with high-performance async processing
     */
    public function intelligentSync(SyncEventProtocolRequest $request)
    {
        $deviceId = $request->validated()['events'][0]['device_id'];
        $workerId = $request->worker_id;
        $syncData = $request->validated()['events'];

        // Determine if this is a new sync or resume
        $syncType = DetermineSyncTypeAction::handle($deviceId, $syncData);

        if ($syncType === SyncType::RESUME) {
            return $this->handleResumeSync($deviceId, $syncData);
        }

        // Prepare sync session and process events
        $syncContext = PrepareSyncSessionAction::handle($deviceId, $workerId, $syncData);

        // Process and validate events
        $validationResult = $this->processAndValidateEventsAction->handle($syncContext);

        if (! $validationResult['is_valid']) {
            return SyncResponseBuilder::validationError($syncContext, $validationResult);
        }

        // Dispatch sync job for valid events
        return DispatchSyncJobAction::handle($syncContext, $validationResult['valid_events'], $request);
    }

    /**
     * Handle resume sync scenario
     */
    private function handleResumeSync(string $deviceId, array $syncData): \Illuminate\Http\JsonResponse
    {
        try {
            $result = $this->autoSyncService->resumeAutoSync($deviceId);

            if ($result['status'] === 'resumed') {
                // Process the resumed sync data
                $sessionId = $result['session_id'];
                $processResult = $this->syncService->processSyncData($sessionId, $syncData);

                return SyncResponseBuilder::resumeSuccess([
                    'status' => 'resumed_and_processed',
                    'session_id' => $sessionId,
                    'sync_type' => 'resume',
                    'checkpoint' => $result['checkpoint'],
                    'processed_events' => count($syncData),
                    'result' => $processResult,
                ]);
            }

            return response()->json($result, 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'resume_failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
