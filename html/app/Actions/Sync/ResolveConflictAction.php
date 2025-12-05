<?php

namespace App\Actions\Sync;

use App\DTOs\Conflict;
use App\DTOs\ResolutionStrategy;
use App\DTOs\SyncItem;
use App\Enums\ConflictType;
use App\Services\Sync\UnresolvedConflictException;

final class ResolveConflictAction
{
    public static function handle(Conflict $conflict): SyncItem
    {
        return match ($conflict->type) {
            ConflictType::CONCURRENT_MODIFICATION => self::resolveConcurrent($conflict),
            ConflictType::DUPLICATE_DATA => self::resolveDuplicate($conflict),
            ConflictType::DIVERGED_HISTORY => self::resolveDiverged($conflict),
            default => $conflict->local,
        };
    }

    private static function resolveConcurrent(Conflict $conflict): SyncItem
    {
        switch ($conflict->resolution) {
            case ResolutionStrategy::LATEST_WINS:
                return $conflict->local->timestamp > $conflict->remote->timestamp
                    ? $conflict->local : $conflict->remote;

            case ResolutionStrategy::PRIORITIZE_SUPERVISOR:
                return ($conflict->local->userRole ?? '') === 'supervisor'
                    ? $conflict->local : $conflict->remote;

            case ResolutionStrategy::MERGE_WITH_USER_INTERVENTION:
                return self::performAutomaticMerge($conflict);

            default:
                throw new UnresolvedConflictException($conflict);
        }
    }

    private static function performAutomaticMerge(Conflict $conflict): SyncItem
    {
        // Implement field-level merging logic
        $merged = clone $conflict->local;

        // Merge non-conflicting fields automatically
        foreach ($conflict->remote->data as $field => $value) {
            if (! isset($conflict->local->data[$field])) {
                $merged->data[$field] = $value;
            } elseif ($conflict->local->data[$field] === $value) {
                // Same value, no conflict
                continue;
            } else {
                // Actual conflict - mark for manual resolution
                $merged->conflictedFields[$field] = [
                    'local' => $conflict->local->data[$field],
                    'remote' => $value,
                ];
            }
        }

        $merged->mergeStatus = 'partial_auto_merge';

        return $merged;
    }

    private static function resolveDuplicate(Conflict $conflict): SyncItem
    {
        // For duplicates, prefer the one with more complete data
        $localCompleteness = self::calculateCompleteness($conflict->local);
        $remoteCompleteness = self::calculateCompleteness($conflict->remote);

        return $localCompleteness >= $remoteCompleteness ? $conflict->local : $conflict->remote;
    }

    private static function resolveDiverged(Conflict $conflict): SyncItem
    {
        // For diverged histories, require manual intervention
        throw new UnresolvedConflictException($conflict, 'Diverged history requires manual resolution');
    }

    private static function calculateCompleteness(SyncItem $item): int
    {
        $score = 0;
        $data = $item->data;

        // Count non-null fields
        foreach ($data as $value) {
            if ($value !== null && $value !== '') {
                $score++;
            }
        }

        // Bonus for recent updates
        if ($item->timestamp > now()->subHours(24)) {
            $score += 2;
        }

        return $score;
    }
}
