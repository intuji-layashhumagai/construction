<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'status',
        'supervisor_id',
        'password',
        'pin_code',
        'pin_required',
        'public_key',

    ];

    protected function casts(): array
    {
        return [
            'pin_required' => 'boolean',
        ];
    }

    protected $hidden = [
        'password',
        'pin_code',
        'public_key',
    ];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Worker::class, 'supervisor_id');
    }

    public function workerDevices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class);
    }
}
