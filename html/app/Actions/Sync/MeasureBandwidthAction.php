<?php

namespace App\Actions\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MeasureBandwidthAction
{
    private const CACHE_KEY = 'bandwidth_measurements';

    private const TEST_URL = 'https://www.google.com/favicon.ico'; // Small test endpoint

    private const TEST_SIZE_BYTES = 1000; // Approximate response size

    public static function handle(): int
    {
        try {
            $measurement = self::performBandwidthTest();
            self::storeMeasurement($measurement);

            return $measurement;
        } catch (\Exception $e) {
            Log::warning('Bandwidth measurement failed, using cached value', [
                'error' => $e->getMessage(),
            ]);

            return self::getAverageBandwidth();
        }
    }

    public static function getAverageBandwidth(): int
    {
        $measurements = Cache::get(self::CACHE_KEY, []);

        if (empty($measurements)) {
            return 1000000; // Default 1MB/s
        }

        return (int) (array_sum($measurements) / count($measurements));
    }

    public static function getCurrentBandwidth(): int
    {
        $measurements = Cache::get(self::CACHE_KEY, []);

        return end($measurements) ?: 1000000;
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
        return max(50000, min(50000000, $bandwidth)); // 50KB/s to 50MB/s
    }

    private static function storeMeasurement(int $measurement): void
    {
        $measurements = Cache::get(self::CACHE_KEY, []);
        $measurements[] = $measurement;

        // Keep only recent measurements
        if (count($measurements) > 10) {
            $measurements = array_slice($measurements, -10);
        }

        Cache::put(self::CACHE_KEY, $measurements, now()->addMinutes(30));
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
