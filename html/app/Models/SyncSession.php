<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncSession extends Model
{
    // Define the fillable attributes
    protected $fillable = [
        'device_id',
        'worker_id',
        'direction',
        'status',
        'vector_clock_state',
        'last_checkpoint',
        'bytes_transferred',
        'start_time',
        'last_activity_time',
    ];

    // Cast JSON fields
    protected function casts(): array
    {
        return [
            'vector_clock_state' => 'array',
            'last_checkpoint' => 'array',
            'start_time' => 'datetime',
            'last_activity_time' => 'datetime',
        ];
    }

    // Define relationships
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
