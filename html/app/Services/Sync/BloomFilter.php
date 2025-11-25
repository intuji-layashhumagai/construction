<?php

namespace App\Services\Sync;

class BloomFilter
{
    private array $bits;

    // todo: calculate the size and hashCount
    private int $size;

    private int $hashCount = 1000;

    public function __construct()
    {
        $this->bits = array_fill(0, $this->size, false);
    }

    public function add(string $item): void
    {
        $hashes = $this->getHashes($item);

        foreach ($hashes as $hash) {
            $this->bits[$hash % $this->size] = true;
        }
    }

    /**
     * Generates an array of hash values based on the provided string item.
     *
     * This function computes two hashes using the CRC32 algorithm: one for
     * the original string and another for the reversed string. It then combines
     * these hashes to create a series of unique, positive hash values. The
     * number of hashes generated is determined by the class property `$this->hashCount`.
     *
     * @param  string  $item  The string input for which to generate hash values.
     * @return array An array of unique hash values derived from the input string.
     */
    private function getHashes(string $item): array
    {
        $hashes = [];
        $hash1 = crc32($item);
        $hash2 = crc32(strrev($item));

        for ($i = 0; $i < $this->hashCount; $i++) {
            $hashes[] = abs($hash1 + $i * $hash2);
        }

        return $hashes;
    }
}
