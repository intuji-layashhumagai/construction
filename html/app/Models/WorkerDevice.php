<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'worker_id',
        'device_id',
        'login_at',
        'last_vc_sent',
        'logout_at',
        'certificate_serial',
        'certificate_issue_time',
        'session_status',
        'events_created',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
            'events_created' => 'integer',
            'last_vc_sent' => 'array',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
