<?php

namespace App\Actions\Sync;

use App\Actions\Event\ProcessSingleEventAction;
use App\DTOs\Conflict;
use App\DTOs\SyncItem;
use App\Enums\ConflictType;
use App\Enums\ResolutionStrategy;

final class DetectConflictAction
{
    public static function handle(SyncItem $local, ?SyncItem $remote, ProcessSingleEventAction $vectorClock): array
    {
        $conflicts = [];
        if (empty($local) || empty($remote)) {
            return $conflicts;
        }

        if (self::isSameLogicalItem($local, $remote)) {
            $conflict = self::analyzeConflict($local, $remote, $vectorClock);
            if ($conflict) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    private static function analyzeConflict(SyncItem $local, SyncItem $remote, ProcessSingleEventAction $vectorClock): ?Conflict
    {
        $comparison = ProcessSingleEventAction::compareVectorClocks($local->vectorClock, $remote->vectorClock);

        if ($comparison === 'concurrent') {
            // todo: implement suggestion based on business rules
            return new Conflict([
                'type' => ConflictType::CONCURRENT_MODIFICATION,
                'local' => $local,
                'remote' => $remote,
                'resolution' => 'suggestion to resolve conflict',
            ]);
        }

        if ($comparison === 'diverged') {
            return new Conflict([
                'type' => ConflictType::DIVERGED_HISTORY,
                'local' => $local,
                'remote' => $remote,
                'resolution' => ResolutionStrategy::MERGE_WITH_USER_INTERVENTION,
            ]);
        }

        return null;
    }

    private static function isSameLogicalItem(SyncItem $ItemA, SyncItem $ItemB): bool
    {
        return $ItemA->logicalId === $ItemB->logicalId &&
            $ItemA->type === $ItemB->type &&
            $ItemA->entityId === $ItemB->entityId;
    }
}
