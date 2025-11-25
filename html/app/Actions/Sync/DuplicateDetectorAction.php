<?php

namespace App\Actions\Sync;

use App\DTOs\SyncItem;
use App\Services\Sync\BloomFilter;

final class DuplicateDetectorAction
{
    private static BloomFilter $bloomFilter;

    private static array $recentIds = [];

    private static int $recentIdsWindow = 100;

    public static function init(int $capacity = 100000, float $falsePositiveRate = 0.01): void
    {
        self::$bloomFilter = BloomFilterAction::create($capacity, $falsePositiveRate);
    }

    public static function isDuplicate(SyncItem $item): bool
    {
        $itemId = self::generateItemId($item);

        // Quick check with bloom filter
        if (BloomFilterAction::possiblyContains(self::$bloomFilter, $itemId)) {
            // Verify against recent IDs
            if (in_array($itemId, self::$recentIds)) {
                return true;
            }

            // todo: Check database for if the event exist

        }

        // Add to tracking structures
        BloomFilterAction::add(self::$bloomFilter, $itemId);
        self::addToRecentIds($itemId);

        return false;
    }

    private static function generateItemId(SyncItem $item): string
    {
        return hash('sha256', json_encode([
            'type' => $item->type,
            'device_id' => $item->deviceId,
            'logical_id' => $item->logicalId,
            'content_hash' => $item->contentHash,
            'entity_id' => $item->entityId,
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
