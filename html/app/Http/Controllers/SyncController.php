<?php

namespace App\Http\Controllers;

use App\Enums\SyncDirection;
use App\Http\Requests\SyncEventRequest;
use App\Services\Sync\SyncProtocol;

class SyncController extends Controller
{
    public function __construct(private readonly SyncProtocol $syncProtocol) {}

    public function sync(SyncEventRequest $request)
    {
        $deviceId = $request->validated()['events'][0]['device_id'];
        $workerId = $request->worker_id;
        $initiatedSync = $this->syncProtocol->initiateSync($deviceId, SyncDirection::UPLOAD, $workerId);
        return $initiatedSync;
    }
}
