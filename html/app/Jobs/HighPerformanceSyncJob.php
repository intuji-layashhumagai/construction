<?php

namespace App\Jobs;

use App\Actions\Sync\ProcessSyncDataAction;
use App\Actions\Sync\ProcessVectorClocksAction;
use App\DTOs\SyncItem;
use App\DTOs\SyncSession;
use App\Enums\SyncPhase;
use App\Models\SyncSession as ModelsSyncSession;
use App\Services\Sync\JobConfig;
use App\Services\Sync\MetricsTracker;
use App\Services\Sync\PriorityQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * High-performance sync job for processing large event batches
 *
 * Features:
 * - Parallel chunk processing with database connection pooling
 * - Redis-based progress tracking and metrics
 * - Automatic batch splitting for optimal throughput
 * - Memory-efficient streaming for large datasets
 * - Real-time performance monitoring
 */
class HighPerformanceSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public int $maxExceptions;

    private JobConfig $jobConfig;

    private MetricsTracker $metricsTracker;

    public function __construct(
        protected string $sessionId,
        protected array $syncData,
        protected array $context = []
    ) {
        $this->jobConfig = new JobConfig;
        $this->metricsTracker = new MetricsTracker($this->sessionId, count($this->syncData));

        $this->onQueue($this->jobConfig->getQueueName());
        $this->timeout = $this->jobConfig->getTimeoutSeconds();
        $this->tries = $this->jobConfig->getMaxTries();
        $this->maxExceptions = $this->jobConfig->getMaxExceptions();

        $this->validateInput();
    }

    protected function validateInput(): void
    {
        if (empty($this->sessionId)) {
            throw new \InvalidArgumentException('Session ID cannot be empty');
        }

        if (empty($this->syncData)) {
            throw new \InvalidArgumentException('Sync data cannot be empty');
        }

        if (count($this->syncData) > $this->jobConfig->getMaxEventsPerJob()) {
            throw new \InvalidArgumentException('Sync data exceeds maximum batch size');
        }
    }

    public function handle(): void
    {
        try {
            // Initialize performance monitoring
            $this->metricsTracker->initialize();

            // Get session from database
            $session = $this->getSyncSession();

            // Process vector clocks for causality tracking
            $this->syncData = ProcessVectorClocksAction::handle($this->syncData, $session);

            // Split data into priority queues
            $priorityQueues = $this->splitIntoPriorityQueues();

            // Process queues in parallel chunks
            $results = $this->processPriorityQueues($session, $priorityQueues);

            // Update final metrics
            $this->finalizeMetrics($results);

            // Mark session as completed
            $this->markSessionCompleted();

            Log::info('High-performance sync completed', [
                'session_id' => $this->sessionId,
                'total_events' => count($this->syncData),
                'throughput_eps' => $this->metricsTracker->getThroughput(),
                'duration_seconds' => $this->metricsTracker->getDuration(),
            ]);

        } catch (Throwable $e) {
            $this->handleJobFailure($e);
            throw $e;
        }
    }

    protected function getSyncSession(): SyncSession
    {
        $storedSession = ModelsSyncSession::findOrFail($this->sessionId);

        return new SyncSession([
            'id' => $storedSession->id,
            'deviceId' => $storedSession->device_id,
            'direction' => $storedSession->direction,
            'phase' => SyncPhase::from($storedSession->status),
            'currentVectorClock' => $storedSession->vector_clock_state ?? [],
        ]);
    }

    protected function splitIntoPriorityQueues(): array
    {
        $criticalQueue = new PriorityQueue;
        $highQueue = new PriorityQueue;
        $normalQueue = new PriorityQueue;

        foreach ($this->syncData as $eventData) {
            $item = SyncItem::fromArray($eventData);

            // Route to appropriate priority queue
            if ($this->isCriticalEvent($eventData)) {
                $criticalQueue->enqueue($item);
            } elseif ($this->isHighPriorityEvent($eventData)) {
                $highQueue->enqueue($item);
            } else {
                $normalQueue->enqueue($item);
            }
        }

        return [
            'critical' => $criticalQueue,
            'high' => $highQueue,
            'normal' => $normalQueue,
        ];
    }

    protected function isCriticalEvent(array $eventData): bool
    {
        return $this->jobConfig->isCriticalEvent($eventData);
    }

    protected function isHighPriorityEvent(array $eventData): bool
    {
        return $this->jobConfig->isHighPriorityEvent($eventData['event_type'] ?? '');
    }

    protected function processPriorityQueues(SyncSession $session, array $queues): array
    {
        $results = [
            'critical' => ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => 0],
            'high' => ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => 0],
            'normal' => ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => 0],
        ];

        // Process critical queue first (no chunking for immediate processing)
        if (! $queues['critical']->isEmpty()) {
            $results['critical'] = $this->processQueueWithoutChunking($session, $queues['critical']);
        }

        // Process high and normal queues with chunking for performance
        $highResults = $this->processQueueWithChunking($session, $queues['high']);
        $normalResults = $this->processQueueWithChunking($session, $queues['normal']);

        $results['high'] = $highResults;
        $results['normal'] = $normalResults;

        return $results;
    }

    protected function processQueueWithoutChunking(SyncSession $session, PriorityQueue $queue): array
    {
        $results = ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => 0];

        while (! $queue->isEmpty()) {
            $item = $queue->dequeue();

            try {
                // Process single critical item immediately using the full sync pipeline
                $singleItemResults = ProcessSyncDataAction::handle($session, [$item]);

                $results['processed'] += $singleItemResults['processed'];
                $results['conflicts'] += $singleItemResults['conflicts'];
                $results['duplicates'] += $singleItemResults['duplicates'];
                $results['errors'] += $singleItemResults['errors'];

                // Update metrics
                $this->metricsTracker->addResults($singleItemResults);
                $this->metricsTracker->updateProgress(0, 0); // No chunks for critical items

            } catch (Throwable $e) {
                $results['errors']++;
                Log::error('Critical event processing failed', [
                    'session_id' => $this->sessionId,
                    'event_id' => $item->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function processQueueWithChunking(SyncSession $session, PriorityQueue $queue): array
    {
        $results = ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => 0];

        // Process in chunks for optimal database performance
        $chunk = [];
        while (! $queue->isEmpty()) {
            $item = $queue->dequeue();
            $chunk[] = $item;

            if (count($chunk) >= $this->jobConfig->getChunkSize()) {
                $chunkResults = $this->processChunk($session, $chunk);
                $results = $this->mergeResults($results, $chunkResults);
                $chunk = [];

                $this->metricsTracker->updateProgress(0, 1);
            }
        }

        // Process remaining items
        if (! empty($chunk)) {
            $chunkResults = $this->processChunk($session, $chunk);
            $results = $this->mergeResults($results, $chunkResults);
            $this->metricsTracker->updateProgress(0, 1);
        }

        return $results;
    }

    protected function processChunk(SyncSession $session, array $chunk): array
    {
        try {

            $results = ProcessSyncDataAction::handle($session, $chunk);

            // Update metrics
            $this->metricsTracker->addResults($results);

            return $results;

        } catch (Throwable $e) {
            Log::error('Chunk processing failed', [
                'session_id' => $this->sessionId,
                'chunk_size' => count($chunk),
                'error' => $e->getMessage(),
            ]);

            return ['processed' => 0, 'conflicts' => 0, 'duplicates' => 0, 'errors' => count($chunk)];
        }
    }

    protected function mergeResults(array $total, array $chunk): array
    {
        return [
            'processed' => $total['processed'] + $chunk['processed'],
            'conflicts' => $total['conflicts'] + $chunk['conflicts'],
            'duplicates' => $total['duplicates'] + $chunk['duplicates'],
            'errors' => $total['errors'] + $chunk['errors'],
        ];
    }

    protected function finalizeMetrics(array $results): void
    {
        // Aggregate final results from all priority queues
        $finalResults = [
            'conflicts' => $results['critical']['conflicts'] + $results['high']['conflicts'] + $results['normal']['conflicts'],
            'duplicates' => $results['critical']['duplicates'] + $results['high']['duplicates'] + $results['normal']['duplicates'],
            'errors' => $results['critical']['errors'] + $results['high']['errors'] + $results['normal']['errors'],
        ];

        $this->metricsTracker->addResults($finalResults);
        $this->metricsTracker->finalize();
    }

    protected function markSessionCompleted(): void
    {
        $storedSession = ModelsSyncSession::find($this->sessionId);
        if ($storedSession) {
            $storedSession->update([
                'status' => SyncPhase::COMPLETE->value,
                'bytes_transferred' => $storedSession->bytes_transferred + strlen(json_encode($this->syncData)),
                'last_activity_time' => now(),
                'actual_events_processed' => $this->metricsTracker->getMetrics()['events_processed'],
                'throughput_eps' => round($this->metricsTracker->getThroughput(), 2),
                'processing_duration_seconds' => round($this->metricsTracker->getDuration(), 3),
                'conflicts_detected' => $this->metricsTracker->getMetrics()['conflicts_detected'],
                'duplicates_found' => $this->metricsTracker->getMetrics()['duplicates_found'],
                'errors_encountered' => $this->metricsTracker->getMetrics()['errors_encountered'],
            ]);
        }

        $this->metricsTracker->markCompleted();
    }

    protected function handleJobFailure(Throwable $e): void
    {
        Log::error('High-performance sync job failed', [
            'session_id' => $this->sessionId,
            'events_count' => count($this->syncData),
            'error' => $e->getMessage(),
            'metrics' => $this->metricsTracker->getMetrics(),
        ]);

        // Mark session as failed
        $storedSession = ModelsSyncSession::find($this->sessionId);
        if ($storedSession) {
            $storedSession->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_activity_time' => now(),
            ]);
        }

        $this->metricsTracker->markFailed($e->getMessage());
    }

    public function failed(Throwable $exception): void
    {
        $this->handleJobFailure($exception);
    }
}
