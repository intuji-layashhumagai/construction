<?php

namespace App\Services\Sync;

use App\DTOs\SyncItem;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

class SyncOperationImplementation implements SyncOperation
{
    private SyncItem $syncItem;

    public function __construct(SyncItem $item)
    {
        $this->syncItem = $item;
    }

    public function validate(): void
    {
        if (empty($this->syncItem->id)) {
            throw new \InvalidArgumentException('Sync item must have a valid ID');
        }
    }

    public function execute(): void
    {
        Log::debug('Processing sync item', [
            'itemId' => $this->syncItem->id,
            'itemType' => $this->syncItem->type,
            'timestamp' => $this->syncItem->timestamp->format('c'),
        ]);

        Event::create([
            'entity_type' => $this->syncItem->entityType ?? 'unknown',
            'entity_id' => $this->syncItem->entityId,
            'event_type' => $this->syncItem->type,
            'device_id' => $this->syncItem->deviceId,
            'worker_id' => $this->syncItem->workerId,
            'event_data' => json_encode($this->syncItem->data),
            'sequence_number' => $this->syncItem->sequenceNumber ?? 1,
            'vector_clock' => $this->syncItem->merged_vector_clock ?? $this->syncItem->vectorClock,
            'server_created_at' => $this->syncItem->timestamp,
        ]);
    }

    public function rollback(): void
    {
        Log::debug('Rolling back sync item', [
            'itemId' => $this->syncItem->id,
        ]);

        // Rollback logic: Delete the created event
        Event::where('entity_id', $this->syncItem->entityId)
            ->where('device_id', $this->syncItem->deviceId)
            ->where('server_created_at', $this->syncItem->timestamp)
            ->delete();
    }

    public function getDescription(): string
    {
        return "Sync operation for item {$this->syncItem->id} of type {$this->syncItem->type}";
    }
}
