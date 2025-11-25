<?php

namespace App\Actions\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MeasureBandwidthAction
{
    private const TEST_URL = 'https://httpbin.org/get'; // Small test endpoint

    private const TEST_SIZE_BYTES = 1024; // Approximate response size

    public static function handle(): int
    {
        try {
            $measurement = self::performBandwidthTest();

            return $measurement;
        } catch (\Exception $e) {
            Log::warning('Bandwidth measurement failed', [
                'error' => $e->getMessage(),
            ]);

            return self::getAverageBandwidth();
        }
    }

    public static function getAverageBandwidth(): int
    {
        return 1000000; // Default 1MB/s
    }

    private static function performBandwidthTest(): int
    {
        $startTime = microtime(true);

        $response = Http::timeout(10)->get(self::TEST_URL);

        if (! $response->successful()) {
            throw new \Exception('Bandwidth test request failed');
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Calculate bandwidth (bytes per second)
        $bandwidth = (int) (self::TEST_SIZE_BYTES / $duration);

        // Ensure reasonable bounds
        return max(50000, min(5000000, $bandwidth)); // 50KB/s to 5MB/s
    }
}
