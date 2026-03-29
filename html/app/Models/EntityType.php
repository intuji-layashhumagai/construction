<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityType extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_type',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all allowed event types for this entity type
     */
    public function allowedEventTypes(): HasMany
    {
        return $this->hasMany(EntityEventType::class);
    }

    /**
     * Check if an event type is allowed for this entity type
     */
    public function allowsEventType(string $eventType): bool
    {
        return $this->allowedEventTypes()->where('event_type', $eventType)->exists();
    }

    /**
     * Get initial/creation events for this entity type
     */
    public function initialEvents()
    {
        return $this->allowedEventTypes()->where('is_initial_event', true);
    }

    /**
     * Scope for active entity types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
