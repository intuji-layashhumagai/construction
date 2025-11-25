<?php

namespace App\Actions\Sync;

use App\Actions\Event\ProcessSingleEventAction;
use App\DTOs\SyncItem;
use App\DTOs\SyncSession;
use App\Services\Sync\SyncOperation;
use App\Services\Sync\SyncOperationImplementation;
use Illuminate\Support\Facades\Log;

final class ProcessSyncDataAction
{
    public static function handle(SyncSession $session, array $syncData): array
    {
        SyncTransactionAction::begin();

        $results = [
            'processed' => 0,
            'conflicts' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        try {
            foreach ($syncData as $itemData) {
                $item = SyncItem::fromArray($itemData);

                // Check for duplicates
                if (DuplicateDetectorAction::isDuplicate($item)) {
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
                }

                $results['processed']++;
                $session->addProcessedItem($item);
            }

            SyncTransactionAction::commit();

            Log::info('Sync data processed successfully', [
                'sessionId' => $session->id,
                'processed' => $results['processed'],
                'conflicts' => $results['conflicts'],
                'duplicates' => $results['duplicates'],
            ]);

        } catch (\Exception $e) {
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
        // todo: query from database to get actual items
        return $item;
    }

    private static function createSyncOperation(SyncItem $item): SyncOperation
    {
        return new SyncOperationImplementation($item);
    }
}
