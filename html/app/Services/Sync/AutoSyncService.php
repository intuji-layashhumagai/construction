<?php

namespace App\Services\Sync;

use App\Actions\Sync\InitiateSyncAction;
use App\DTOs\SyncSession as DTOsSyncSession;
use App\Enums\DataPriority;
use App\Enums\SyncDirection;
use App\Models\Device;
use App\Models\Event;
use App\Models\SyncSession;
use App\Services\SyncScheduler;
use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    public function __construct(
        private readonly SyncProtocol $syncProtocol,
        private readonly SyncScheduler $syncScheduler
    ) {}

    /**
     * Check connectivity and initiate auto-sync for a device
     */
    public function checkAndInitiateAutoSync(string $deviceId): array
    {
        $device = Device::find($deviceId);
        if (! $device) {
            return ['status' => 'error', 'message' => 'Device not found'];
        }

        // Check if there are pending sync items
        $pendingItems = $this->getPendingSyncItems($deviceId);
        $result = [];
        if (empty($pendingItems)) {
            $result = ['status' => 'no_pending', 'message' => 'No pending sync items'];
        } else {
            // Prioritize critical items
            $criticalItems = $this->filterCriticalItems($pendingItems);
            $hasCriticalItems = ! empty($criticalItems);

            // Check if auto-sync should be initiated based on scheduler
            $shouldSync = $this->syncScheduler->shouldInitiateAutoSync($deviceId, $hasCriticalItems);

            if (! $shouldSync) {
                $result = [
                    'status' => 'scheduled',
                    'message' => 'Auto-sync scheduled for later',
                    'next_sync' => $this->syncScheduler->getNextSyncTime($deviceId),
                ];
            } else {
                // Initiate sync session
                $sessionId = $this->initiateAutoSyncSession($deviceId, $hasCriticalItems);

                Log::info('Auto-sync initiated', [
                    'device_id' => $deviceId,
                    'session_id' => $sessionId,
                    'critical_items' => count($criticalItems),
                    'total_items' => count($pendingItems),
                ]);

                $result = [
                    'status' => 'initiated',
                    'session_id' => $sessionId,
                    'critical_items_count' => count($criticalItems),
                    'total_items_count' => count($pendingItems),
                ];
            }
        }

        return $result;
    }

    /**
     * Process auto-sync data with priority handling
     */
    public function processAutoSyncData(string $sessionId, array $syncData): array
    {
        $session = SyncSession::find($sessionId);
        if (! $session) {
            throw new \Exception('Sync session not found');
        }

        // Separate critical and non-critical data
        $criticalData = $this->filterCriticalSyncData($syncData);
        $regularData = array_filter($syncData, function ($item) use ($criticalData) {
            return ! in_array($item, $criticalData);
        });

        $results = [];

        // Process critical data first
        if (! empty($criticalData)) {
            Log::info('Processing critical sync data', [
                'session_id' => $sessionId,
                'critical_count' => count($criticalData),
            ]);

            $criticalResults = $this->syncProtocol->processSyncData(
                $this->createSessionDTO($session),
                $criticalData
            );
            $results['critical'] = $criticalResults;
        }

        // Process regular data
        if (! empty($regularData)) {
            $regularResults = $this->syncProtocol->processSyncData(
                $this->createSessionDTO($session),
                $regularData
            );
            $results['regular'] = $regularResults;
        }

        return $results;
    }

    /**
     * Resume interrupted auto-sync
     */
    public function resumeAutoSync(string $deviceId): array
    {
        // Find the most recent interrupted session for this device
        $interruptedSession = SyncSession::where('device_id', $deviceId)
            ->where('status', 'interrupted')
            ->orderBy('last_activity_time', 'desc')
            ->first();

        if (! $interruptedSession) {
            return ['status' => 'no_session', 'message' => 'No interrupted session found'];
        }

        // Resume from checkpoint
        $resumedSession = $this->syncProtocol->resumeSync($interruptedSession->last_checkpoint);

        Log::info('Auto-sync resumed', [
            'device_id' => $deviceId,
            'session_id' => $resumedSession->id,
            'checkpoint' => $interruptedSession->last_checkpoint,
        ]);

        return [
            'status' => 'resumed',
            'session_id' => $resumedSession->id,
            'checkpoint' => $interruptedSession->last_checkpoint,
        ];
    }

    /**
     * Get pending sync items for a device
     */
    private function getPendingSyncItems(string $deviceId): array
    {
        $events = Event::where('device_id', $deviceId)
            ->where('is_validated', true)
            ->whereNull('quarantined_at')
            ->get();

        return $events->map(function ($event) {
            $priority = match ($event->event_type) {
                'safety_alert' => DataPriority::CRITICAL,
                'time_entry' => DataPriority::HIGH,
                'inventory_update' => DataPriority::MEDIUM,
                default => DataPriority::BACKGROUND,
            };

            return [
                'id' => $event->id,
                'priority' => $priority,
                'type' => $event->event_type,
                'data' => $event->event_data,
            ];
        })->toArray();
    }

    /**
     * Filter critical items from pending sync items
     */
    private function filterCriticalItems(array $items): array
    {
        return array_filter($items, function ($item) {
            return $item['priority'] === DataPriority::CRITICAL;
        });
    }

    /**
     * Filter critical sync data
     */
    private function filterCriticalSyncData(array $syncData): array
    {
        return array_filter($syncData, function ($item) {
            return isset($item['priority']) && $item['priority'] === DataPriority::CRITICAL;
        });
    }

    /**
     * Initiate auto-sync session
     */
    private function initiateAutoSyncSession(string $deviceId, bool $hasCriticalItems): string
    {
        $direction = $hasCriticalItems ? SyncDirection::UPLOAD : SyncDirection::BIDIRECTIONAL;

        return InitiateSyncAction::handle($deviceId, $direction, 'auto-sync-worker');
    }

    /**
     * Create session DTO from model
     */
    private function createSessionDTO(SyncSession $session): \App\DTOs\SyncSession
    {
        return DTOsSyncSession::fromArray($session->toArray());
    }
}
