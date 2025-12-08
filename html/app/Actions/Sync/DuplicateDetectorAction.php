<?php

namespace App\Actions\Sync;

use App\DTOs\SyncItem;
use App\Services\Sync\BloomFilter;
use Illuminate\Support\Facades\DB;

final class DuplicateDetectorAction
{
    private static ?BloomFilter $bloomFilter = null;

    private static array $recentIds = [];

    private static int $recentIdsWindow = 100;

    public static function init(int $capacity = 100000, float $falsePositiveRate = 0.01): void
    {
        self::$bloomFilter = BloomFilterAction::create($capacity, $falsePositiveRate);
    }

    public static function isDuplicate(SyncItem $item): bool
    {
        if (self::$bloomFilter === null) {
            self::init();
        }
        $itemId = self::generateItemId($item);
        $jsonData = json_encode($item->data);

        // Quick check with bloom filter
        if (BloomFilterAction::possiblyContains(self::$bloomFilter, $itemId)) {
            // Verify against recent IDs
            if (in_array($itemId, self::$recentIds)) {
                return true;
            }

            // Check database for existing event with same content using JSONB containment
            $existingEvent = DB::selectOne('
                SELECT COUNT(*) > 0 as exists
                FROM events
                WHERE entity_id = ?
                AND event_type = ?
                AND event_data @> ?::jsonb
                AND ?::jsonb @> event_data
            ', [
                $item->entityId,
                $item->type,
                $jsonData,
                $jsonData,
            ])->exists;

            if ($existingEvent) {
                return true;
            }

        }

        // Add to tracking structures
        BloomFilterAction::add(self::$bloomFilter, $itemId);
        self::addToRecentIds($itemId);

        return false;
    }

    private static function generateItemId(SyncItem $item): string
    {
        // Generate hash based on event content for duplicate detection
        // This ensures events with identical type, entity, and data are detected as duplicates
        return hash('sha256', json_encode([
            'type' => $item->type,
            'entity_id' => $item->entityId,
            'data' => $item->data,  // Include event data for content-based duplicate detection
        ]));
    }

    private static function addToRecentIds(string $itemId): void
    {
        self::$recentIds[] = $itemId;

        if (count(self::$recentIds) > self::$recentIdsWindow) {
            array_shift(self::$recentIds);
        }
    }
}
