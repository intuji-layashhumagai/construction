<?php

namespace App\Http\Controllers;

use App\Enums\SyncDirection;
use App\Http\Requests\SyncEventProtocolRequest;
use App\Http\Requests\SyncEventRequest;
use App\Services\Sync\SyncProtocol;
use App\Services\SyncService;

class SyncController extends Controller
{
    public function __construct(private readonly SyncProtocol $syncProtocol, private readonly SyncService $syncService) {}

    public function sync(SyncEventRequest $request)
    {
        $deviceId = $request->validated()['events'][0]['device_id'];
        $workerId = $request->worker_id;
        $initiatedSync = $this->syncProtocol->initiateSync($deviceId, SyncDirection::UPLOAD, $workerId);

        return $initiatedSync;
    }

    public function syncProcess(SyncEventProtocolRequest $request, string $sessionId)
    {
        $syncData = $request->validated()['events'];
        $processedSyncData = $this->syncService->processSyncData($sessionId, $syncData);

        return $processedSyncData;
    }

    public function syncResume(SyncEventProtocolRequest $request, string $sessionId)
    {
        $syncData = $request->validated()['events'];
        $processedSyncData = $this->syncService->resumeSync($sessionId, $syncData);

        return $processedSyncData;
    }

    public function syncSchedule(SyncEventProtocolRequest $request, string $sessionId)
    {

        $syncData = $request->validated()['events'];
        $processedSyncData = $this->syncService->scheduleSync($sessionId, $syncData);

        return $processedSyncData;
    }
}
