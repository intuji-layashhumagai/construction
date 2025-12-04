<?php

namespace App\Actions\Sync;

use App\DTOs\SyncContext;
use App\Jobs\HighPerformanceSyncJob;
use App\Models\SyncSession;
use App\Services\SyncResponseBuilder;
use Illuminate\Support\Facades\Bus;

final class DispatchSyncJobAction
{
    public static function handle(SyncContext $syncContext, array $validEvents, $request): \Illuminate\Http\JsonResponse
    {
        try {
            $job = new HighPerformanceSyncJob($syncContext->sessionId, $validEvents, [
                'device_id' => $syncContext->deviceId,
                'worker_id' => $syncContext->workerId,
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $jobInstance = Bus::dispatch($job);
            $jobId = is_object($jobInstance) ? $jobInstance->id : $jobInstance;

            // Update session
            $syncContext->sessionModel->update([
                'job_id' => $jobId,
                'status' => 'processing',
                'estimated_events' => count($validEvents),
            ]);

            return SyncResponseBuilder::success($syncContext, $validEvents, $jobId);

        } catch (\Exception $e) {
            // If job dispatch fails, quarantine all events
            info($e);
            $quarantineContext = \App\DTOs\QuarantineContext::create(
                invalidEvents: $syncContext->processedEvents, // All events failed due to job dispatch error
                processedEvents: $syncContext->processedEvents,
                sessionId: $syncContext->sessionId,
                deviceId: $syncContext->deviceId,
                workerId: $syncContext->workerId,
                serverReceivedAt: $syncContext->serverReceivedAt,
                violations: [['message' => 'Job dispatch failed: '.$e->getMessage()]]
            );

            QuarantineInvalidEventsAction::handle($quarantineContext);

            $session = SyncSession::find($syncContext->sessionId);
            if ($session) {
                $session->update([
                    'status' => 'failed',
                    'error_message' => 'Job dispatch failed: '.$e->getMessage(),
                ]);
            }

            return SyncResponseBuilder::dispatchError($syncContext, $e->getMessage());
        }
    }
}
