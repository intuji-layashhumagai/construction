<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'id',
        'event_id',
        'entity_type',
        'entity_id',
        'worker_id',
        'event_type',
        'event_data',
        'device_id',
        'sequence_number',
        'server_created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'server_created_at' => 'datetime',
        ];
    }

    // todo: logics for sequence generation for events and logics for conflict resolution for data

    /**
     * Scope for events related to specific entity
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Replay events to reconstruct entity state
     * Implements requires current state received by replaying events
     */
    public static function replayEventSequence(string $entityType, string $entityId): array
    {
        $events = static::forEntity($entityType, $entityId)
            ->orderBy('sequence_number')
            ->orderBy('server_created_at')
            ->get();

        $state = [];
        foreach ($events as $event) {
            $state = $event->stateCalculations($state);
        }

        return $state;
    }

    /**
     * Handles different event types for materialized views
     */
    protected function stateCalculations(array $currentState): array
    {
        $newState = $currentState;
        $eventType = EventType::tryFrom($this->event_type);

        if (in_array($eventType, [
            EventType::WORKER_CREATED,
            EventType::PROJECT_CREATED,
            EventType::STOCK_CREATED,
            EventType::INVENTORY_CREATED,
        ])) {
            // Initial set data for the events
            $newState = array_merge($newState, $this->event_data);
        } elseif ($eventType === EventType::HOURS_LOGGED) {
            // Accumulate hours calculated
            $newState['hours_worked'] = ($newState['hours_worked'] ?? 0) + ($this->event_data['hours_worked'] ?? 0);
        } elseif ($eventType === EventType::STOCK_USED) {
            // Subtract from quantity
            $newState['quantity'] = ($newState['quantity'] ?? 0) - ($this->event_data['quantity_used'] ?? 0);
        } elseif ($eventType === EventType::STOCK_ADJUSTED) {
            // Add or subtract stocks
            $newState['quantity'] = ($newState['quantity'] ?? 0) + ($this->event_data['adjustment'] ?? 0);
        } else {
            $newState = array_merge($newState, $this->event_data);
        }

        return $newState;
    }
}
