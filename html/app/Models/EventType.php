<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventType extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_type',
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
     * Get all entity types that allow this event type
     */
    public function allowedEntityTypes(): BelongsToMany
    {
        return $this->belongsToMany(EntityType::class, 'entity_event_types')
            ->withPivot(['is_initial_event', 'requires_approval', 'priority'])
            ->withTimestamps();
    }

    /**
     * Scope for active event types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find event type by enum value
     */
    public static function findByType(string $eventType): ?self
    {
        return static::where('event_type', $eventType)->first();
    }
}
