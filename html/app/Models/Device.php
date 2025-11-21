<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'model',
        'os_version',
        'status',
        'storage_available',
        'network_type',
        'public_key',
        'certificate_expires_at',

    ];

    protected function casts(): array
    {
        return [
            'certificate_expires_at' => 'datetime',
            'storage_available' => 'integer',
        ];
    }

    protected $hidden = [

        'public_key',
    ];

    public function workerDevices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class);
    }
}
