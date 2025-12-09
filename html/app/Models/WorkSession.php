<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'worker_id',
        'current_device_id',
        'status',
        'total_hours',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'total_hours' => 'decimal:2',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function currentDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'current_device_id');
    }
}
