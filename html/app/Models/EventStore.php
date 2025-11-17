<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventStore extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'event_store';

    protected $fillable = [
        'event_id',
        'worker_id',
        'event_type',
        'event_data',
        'device_id',
        'sequence_number',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
        ];
    }
}
