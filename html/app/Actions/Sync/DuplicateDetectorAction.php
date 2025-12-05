<?php

namespace App\Actions\Sync;

use App\DTOs\SyncItem;
use App\Models\Event;
use App\Services\Sync\BloomFilter;

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

        // Quick check with bloom filter
        if (BloomFilterAction::possiblyContains(self::$bloomFilter, $itemId)) {
            // Verify against recent IDs
            if (in_array($itemId, self::$recentIds)) {
                return true;
            }

            // Check database for existing event with same content
            $existingEvent = Event::where('entity_id', $item->entityId)
                ->where('event_type', $item->type)
                ->where('event_data', json_encode($item->data))
                ->exists();

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
