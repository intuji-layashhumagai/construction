<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Redis;

final class MetricsTracker
{
    private array $metrics;
    private string $sessionId;
    private float $startTime;

    public function __construct(string $sessionId, int $totalEvents)
    {
        $this->sessionId = $sessionId;
        $this->startTime = microtime(true);
        $this->metrics = [
            'start_time' => $this->startTime,
            'end_time' => null,
            'events_total' => $totalEvents,
            'events_processed' => 0,
            'chunks_processed' => 0,
            'conflicts_detected' => 0,
            'duplicates_found' => 0,
            'errors_encountered' => 0,
            'throughput_eps' => 0.0,
        ];
    }

    public function initialize(): void
    {
        Redis::hmset("sync_metrics:{$this->sessionId}", [
            'status' => 'processing',
            'start_time' => $this->metrics['start_time'],
            'events_total' => $this->metrics['events_total'],
            'events_processed' => 0,
            'chunks_processed' => 0,
            'throughput_eps' => 0.0,
        ]);
    }

    public function updateProgress(int $eventsProcessed, int $chunksProcessed): void
    {
        $this->metrics['events_processed'] += $eventsProcessed;
        $this->metrics['chunks_processed'] += $chunksProcessed;

        $elapsed = microtime(true) - $this->startTime;
        $this->metrics['throughput_eps'] = $this->metrics['events_processed'] / max($elapsed, 0.001);

        Redis::hmset("sync_metrics:{$this->sessionId}", [
            'events_processed' => $this->metrics['events_processed'],
            'chunks_processed' => $this->metrics['chunks_processed'],
            'throughput_eps' => round($this->metrics['throughput_eps'], 2),
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function addResults(array $results): void
    {
        $this->metrics['conflicts_detected'] += $results['conflicts'] ?? 0;
        $this->metrics['duplicates_found'] += $results['duplicates'] ?? 0;
        $this->metrics['errors_encountered'] += $results['errors'] ?? 0;
        $this->metrics['events_processed'] += $results['processed'] ?? 0;
    }

    public function finalize(): void
    {
        $this->metrics['end_time'] = microtime(true);
        $totalTime = $this->metrics['end_time'] - $this->startTime;
        $this->metrics['throughput_eps'] = $this->metrics['events_total'] / max($totalTime, 0.001);
    }

    public function markCompleted(): void
    {
        Redis::hmset("sync_metrics:{$this->sessionId}", [
            'status' => 'completed',
            'end_time' => $this->metrics['end_time'],
            'total_events' => $this->metrics['events_total'],
            'events_processed' => $this->metrics['events_processed'],
            'conflicts_detected' => $this->metrics['conflicts_detected'],
            'duplicates_found' => $this->metrics['duplicates_found'],
            'errors_encountered' => $this->metrics['errors_encountered'],
            'throughput_eps' => round($this->metrics['throughput_eps'], 2),
            'duration_seconds' => round($this->metrics['end_time'] - $this->startTime, 3),
        ]);
    }

    public function markFailed(string $error): void
    {
        Redis::hmset("sync_metrics:{$this->sessionId}", [
            'status' => 'failed',
            'error' => $error,
            'failed_at' => now()->toISOString(),
        ]);
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getThroughput(): float
    {
        return $this->metrics['throughput_eps'];
    }

    public function getDuration(): float
    {
        return $this->metrics['end_time'] ? $this->metrics['end_time'] - $this->startTime : 0.0;
    }
}
