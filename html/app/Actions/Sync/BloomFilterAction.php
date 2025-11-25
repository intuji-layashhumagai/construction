<?php

namespace App\Actions\Sync;

use App\Services\Sync\BloomFilter;

final class BloomFilterAction
{
    /*
    * Create a new BloomFilter instance.
     *
     * @param int $capacity The expected number of items to be added to the filter.
     * @param float $falsePositiveRate The desired false positive rate (default: 0.01).
     * @return BloomFilter The newly created BloomFilter instance.
     */
    public static function create(int $capacity, float $falsePositiveRate = 0.01): BloomFilter
    {
        return new BloomFilter($capacity, $falsePositiveRate);
    }

    public static function add(BloomFilter $filter, string $item): void
    {
        $filter->add($item);
    }
}
