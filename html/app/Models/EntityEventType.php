<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityEventType extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_type_id',
        'event_type_id',
        'name',
        'description',
        'is_initial_event',
        'requires_approval',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_initial_event' => 'boolean',
            'requires_approval' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * Get the entity type this relationship belongs to
     */
    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }

    /**
     * Get the event type this relationship belongs to
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * Scope for initial/creation events
     */
    public function scopeInitialEvents($query)
    {
        return $query->where('is_initial_event', true);
    }

    /**
     * Scope for events requiring approval
     */
    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    /**
     * Scope by priority level
     */
    public function scopeByPriority($query, int $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if this event type is allowed for the given entity type
     */
    public static function isAllowed(string $entityType, string $eventType): bool
    {
        return static::whereHas('entityType', function ($query) use ($entityType) {
            $query->where('entity_type', $entityType)->where('is_active', true);
        })
            ->whereHas('eventType', function ($query) use ($eventType) {
                $query->where('event_type', $eventType)->where('is_active', true);
            })
            ->exists();
    }

    /**
     * Get the relationship record for specific entity and event types
     */
    public static function findRelationship(string $entityType, string $eventType): ?self
    {
        return static::whereHas('entityType', function ($query) use ($entityType) {
            $query->where('entity_type', $entityType)->where('is_active', true);
        })
            ->whereHas('eventType', function ($query) use ($eventType) {
                $query->where('event_type', $eventType)->where('is_active', true);
            })
            ->first();
    }
}
