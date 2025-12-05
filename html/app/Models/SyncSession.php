<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncSession extends Model
{
    use HasUuids;

    // Define the fillable attributes
    protected $fillable = [
        'id',
        'device_id',
        'worker_id',
        'direction',
        'status',
        'vector_clock_state',
        'last_checkpoint',
        'bytes_transferred',
        'start_time',
        'last_activity_time',
        'job_id',
        'estimated_events',
        'actual_events_processed',
        'throughput_eps',
        'processing_duration_seconds',
        'conflicts_detected',
        'duplicates_found',
        'errors_encountered',
    ];

    // Cast JSON fields
    protected function casts(): array
    {
        return [
            'vector_clock_state' => 'array',
            'last_checkpoint' => 'array',
            'start_time' => 'datetime',
            'last_activity_time' => 'datetime',
            'estimated_events' => 'integer',
            'actual_events_processed' => 'integer',
            'throughput_eps' => 'decimal:2',
            'processing_duration_seconds' => 'decimal:3',
            'conflicts_detected' => 'integer',
            'duplicates_found' => 'integer',
            'errors_encountered' => 'integer',
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

    /**
     * Get current vector clock (alias for vector_clock_state for VectorClockAction compatibility)
     */
    public function getCurrentVectorClockAttribute(): array
    {
        return $this->vector_clock_state ?? [];
    }

    /**
     * Set current vector clock (alias for vector_clock_state for VectorClockAction compatibility)
     */
    public function setCurrentVectorClockAttribute(array $clock): void
    {
        $this->vector_clock_state = $clock;
    }
}
