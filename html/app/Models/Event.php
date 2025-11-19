<?php

namespace App\Models;

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

    /**
     * Scope for events related to specific entity
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }
}
