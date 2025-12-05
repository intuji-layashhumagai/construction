<?php

namespace App\Models;

use App\Enums\QuarantinedEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantinedEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'session_id',
        'device_id',
        'worker_id',
        'event_data',
        'validation_errors',
        'vector_clock',
        'device_timestamp',
        'server_received_at',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'migration_applied',
        'migrated_event_id',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'validation_errors' => 'array',
            'vector_clock' => 'array',
            'device_timestamp' => 'datetime',
            'server_received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'migration_applied' => 'array',
            'status' => QuarantinedEventStatus::class,
        ];
    }

    /**
     * Get the device that owns the quarantined event.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the worker that owns the quarantined event.
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /**
     * Get the user who reviewed the quarantined event.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the sync session associated with this quarantined event.
     */
    public function syncSession(): BelongsTo
    {
        return $this->belongsTo(SyncSession::class, 'session_id');
    }

    /**
     * Scope for pending review events.
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', QuarantinedEventStatus::PENDING_REVIEW);
    }

    /**
     * Scope for events by device.
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope for events by worker.
     */
    public function scopeForWorker($query, $workerId)
    {
        return $query->where('worker_id', $workerId);
    }

    /**
     * Mark event as approved.
     */
    public function markApproved(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => QuarantinedEventStatus::APPROVED,
            'reviewed_by' => $userId,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark event as rejected.
     */
    public function markRejected(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => QuarantinedEventStatus::REJECTED,
            'reviewed_by' => $userId,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark event as migrated.
     */
    public function markMigrated(string $migratedEventId, array $migrationData, ?int $userId = null): void
    {
        $this->update([
            'status' => QuarantinedEventStatus::MIGRATED,
            'migrated_event_id' => $migratedEventId,
            'migration_applied' => $migrationData,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }
}
