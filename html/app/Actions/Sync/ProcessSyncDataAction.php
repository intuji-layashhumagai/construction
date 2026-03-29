<?php

namespace App\Actions\Sync;

use App\Actions\Event\ProcessSingleEventAction;
use App\DTOs\SyncItem;
use App\DTOs\SyncSession;
use App\Models\Event;
use App\Services\Sync\PriorityQueue;
use App\Services\Sync\SyncOperation;
use App\Services\Sync\SyncOperationImplementation;
use Illuminate\Support\Facades\Log;

final class ProcessSyncDataAction
{
    private static array $pendingMerges = [];

    public static function handle(SyncSession $session, array $syncData): array
    {
        self::$pendingMerges = []; // Reset for this batch
        SyncTransactionAction::begin();

        $results = [
            'processed' => 0,
            'conflicts' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];
        $priorityQueue = new PriorityQueue;

        try {
            // Enqueue all items with priority
            foreach ($syncData as $itemData) {
                $item = is_array($itemData) ? SyncItem::fromArray($itemData) : $itemData;
                $priorityQueue->enqueue($item);
            }

            // Process in priority order
            while (! $priorityQueue->isEmpty()) {
                $item = $priorityQueue->dequeue();

                // Check for duplicates
                $duplicateResults = DuplicateDetectorAction::isDuplicate($item);

                if ($duplicateResults) {
                    // Handle merging for specific duplicate types
                    self::$pendingMerges[] = $item;
                    Log::info('Event flagged as duplicate', [
                        'entityId' => $item->entityId,
                        'eventType' => $item->type,
                        'sessionId' => $session->id,
                    ]);
                    $results['duplicates']++;

                    continue;
                }

                // Detect conflicts
                $conflicts = DetectConflictAction::handle($item, self::getExistingItem($item), new ProcessSingleEventAction);
                if (! empty($conflicts)) {
                    foreach ($conflicts as $conflict) {
                        $resolvedItem = ResolveConflictAction::handle($conflict);
                        $operation = self::createSyncOperation($resolvedItem);
                        SyncTransactionAction::addOperation($operation);
                        $results['conflicts']++;
                    }
                } else {
                    // No conflicts detected
                    $operation = self::createSyncOperation($item);
                    SyncTransactionAction::addOperation($operation);
                    Log::info('Event queued for storage', [
                        'entityId' => $item->entityId,
                        'eventType' => $item->type,
                        'sessionId' => $session->id,
                    ]);
                }

                $results['processed']++;
                $session->addProcessedItem($item);
            }

            SyncTransactionAction::commit();
            self::processPendingMerges();

            // Create checkpoint
            $checkpoint = CreateCheckpointAction::handle($session);
            $session->lastCheckpoint = $checkpoint;

            Log::info('Sync data processed successfully', [
                'sessionId' => $session->id,
                'processed' => $results['processed'],
                'conflicts' => $results['conflicts'],
                'duplicates' => $results['duplicates'],
            ]);

        } catch (\Exception $e) {
            info(['ERROR', $e]);
            SyncTransactionAction::rollback();
            $results['errors']++;
            Log::error('Sync processing failed', [
                'sessionId' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $results;
    }

    private static function getExistingItem(SyncItem $item): ?SyncItem
    {
        // Query for existing events with the same entity_id and type
        $existingEvent = Event::where('entity_id', $item->entityId)
            ->where('event_type', $item->type)
            ->orderBy('server_created_at', 'desc')
            ->first();

        if ($existingEvent) {
            // Ensure event_data is an array, handling case where cast might not work
            $eventData = $existingEvent->event_data;
            if (is_string($eventData)) {
                $eventData = json_decode($eventData, true) ?? [];
            }

            return new SyncItem(
                id: $existingEvent->id,
                type: $existingEvent->event_type,
                data: $eventData,
                vectorClock: $existingEvent->vector_clock,
                workerId: $existingEvent->worker_id,
                deviceId: $existingEvent->device_id,
                entityId: $existingEvent->entity_id,
                timestamp: $existingEvent->server_created_at,
                sequenceNumber: $existingEvent->sequence_number
            );
        }

        return null;
    }

    private static function createSyncOperation(SyncItem $item): SyncOperation
    {
        return new SyncOperationImplementation($item);
    }

    private static function processPendingMerges(): void
    {
        foreach (self::$pendingMerges as $duplicateItem) {
            ManageWorkerSessionAction::handle($duplicateItem);
        }
        self::$pendingMerges = [];
    }
}
