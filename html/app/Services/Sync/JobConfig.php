<?php

namespace App\Services\Sync;

use App\Constants\Queue;

final class JobConfig
{
    private array $config;

    public function __construct()
    {
        $this->config = config('project.sync.job', [
            'queue_name' => Queue::HIGH_PERFORMANCE_SYNC,
            'processing' => [
                'chunk_size' => 100,
                'max_chunks_per_job' => 100,
                'max_events_per_job' => 10000,
            ],
            'execution' => [
                'timeout_seconds' => 3600,
                'max_tries' => 2,
                'max_exceptions' => 3,
            ],
            'monitoring' => [
                'metrics_enabled' => true,
                'redis_prefix' => 'sync_metrics',
            ],
        ]);
    }

    public function getQueueName(): string
    {
        return $this->config['queue_name'];
    }

    public function getChunkSize(): int
    {
        return $this->config['processing']['chunk_size'];
    }

    public function getMaxChunksPerJob(): int
    {
        return $this->config['processing']['max_chunks_per_job'];
    }

    public function getMaxEventsPerJob(): int
    {
        return $this->config['processing']['max_events_per_job'] ??
               ($this->getMaxChunksPerJob() * $this->getChunkSize());
    }

    public function getTimeoutSeconds(): int
    {
        return $this->config['execution']['timeout_seconds'];
    }

    public function getMaxTries(): int
    {
        return $this->config['execution']['max_tries'];
    }

    public function getMaxExceptions(): int
    {
        return $this->config['execution']['max_exceptions'];
    }

    public function isMetricsEnabled(): bool
    {
        return $this->config['monitoring']['metrics_enabled'];
    }

    public function getRedisPrefix(): string
    {
        return $this->config['monitoring']['redis_prefix'];
    }

    public function getHighPriorityEventTypes(): array
    {
        return ['payment_made', 'inventory_transfer_completed', 'worker_status_changed'];
    }

    public function isHighPriorityEvent(string $eventType): bool
    {
        return in_array($eventType, $this->getHighPriorityEventTypes());
    }

    public function isCriticalEvent(array $eventData): bool
    {
        return isset($eventData['priority']) && $eventData['priority'] === 'critical';
    }
}
