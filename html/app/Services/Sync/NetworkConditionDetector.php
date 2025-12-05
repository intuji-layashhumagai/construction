<?php

namespace App\Services\Sync;

use App\Enums\NetworkQuality;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class NetworkConditionDetector
{
    private const CACHE_KEY = 'network_conditions';

    public function assessQuality(): NetworkQuality
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached['quality'];
        }

        try {
            $latency = $this->measureLatency();
            $bandwidth = $this->measureBandwidth();
            $stability = $this->measureStability();

            $quality = $this->calculateQuality($latency, $bandwidth, $stability);

            Cache::put(self::CACHE_KEY, [
                'quality' => $quality,
                'latency' => $latency,
                'bandwidth' => $bandwidth,
                'stability' => $stability,
            ], Config::get('project.sync.cache_ttl', 300));

            return $quality;
        } catch (\Exception $e) {
            Log::warning('Network quality assessment failed', [
                'error' => $e->getMessage(),
            ]);

            return NetworkQuality::FAIR; // Default fallback
        }
    }

    public function isOnWifi(): bool
    {
        // Server-side detection is limited; this could be improved with client-side info
        try {
            $latency = $this->measureLatency();

            // Lower latency might indicate wired connection
            return $latency > 50; // Rough heuristic
        } catch (\Exception $e) {
            return false; // Default to not WiFi
        }
    }

    public function getConnectionStability(): float
    {
        $cached = Cache::get(self::CACHE_KEY);

        return $cached['stability'] ?? 0.8; // Default 80%
    }

    private function measureLatency(): int
    {
        $result = Process::run(['ping', '-c', '3', '-W', '2', Config::get('project.sync.test_host', '8.8.8.8')]);

        if (! $result->successful()) {
            throw new \Exception('Ping failed');
        }

        // Parse average latency from output
        preg_match('/rtt min\/avg\/max\/mdev = [\d.]+\/([\d.]+)\//', $result->output(), $matches);

        return isset($matches[1]) ? (int) ($matches[1] * 1000) : 100; // Convert to ms
    }

    private function measureBandwidth(): int
    {
        $start = microtime(true);
        $response = Http::timeout(5)->get(Config::get('project.sync.test_url', 'https://www.google.com/favicon.ico'));
        $end = microtime(true);

        if (! $response->successful()) {
            throw new \Exception('Bandwidth test failed');
        }

        $duration = $end - $start;
        $bytes = strlen($response->body());

        return (int) ($bytes / $duration); // bytes per second
    }

    private function measureStability(): float
    {
        // Simple stability based on consistent ping times
        $latencies = [];
        for ($i = 0; $i < 3; $i++) {
            try {
                $latencies[] = $this->measureLatency();
            } catch (\Exception $e) {
                $latencies[] = 200; // High latency on failure
            }
            usleep(100000); // 100ms delay
        }

        $avg = array_sum($latencies) / count($latencies);
        $variance = array_sum(array_map(fn ($l) => pow($l - $avg, 2), $latencies)) / count($latencies);
        $stdDev = sqrt($variance);

        // Stability score: lower std dev = higher stability
        return max(0.1, min(1.0, 1 - ($stdDev / $avg)));
    }

    private function calculateQuality(int $latency, int $bandwidth, float $stability): NetworkQuality
    {
        $score = 0;

        // Latency scoring (lower is better)
        if ($latency < 20) {
            $score += 40;
        } elseif ($latency < 50) {
            $score += 30;
        } elseif ($latency < 100) {
            $score += 20;
        } elseif ($latency < 200) {
            $score += 10;
        }

        // Bandwidth scoring (higher is better)
        if ($bandwidth > 5000000) {
            $score += 40;
        } // 5MB/s+
        elseif ($bandwidth > 1000000) {
            $score += 30;
        } // 1MB/s+
        elseif ($bandwidth > 500000) {
            $score += 20;
        } // 500KB/s+
        elseif ($bandwidth > 100000) {
            $score += 10;
        } // 100KB/s+

        // Stability scoring
        $score += (int) ($stability * 20);

        if ($score >= 80) {
            return NetworkQuality::EXCELLENT;
        }
        if ($score >= 60) {
            return NetworkQuality::GOOD;
        }
        if ($score >= 40) {
            return NetworkQuality::FAIR;
        }

        return NetworkQuality::POOR;
    }
}
