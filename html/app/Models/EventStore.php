<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'worker_id',
        'event_type',
        'event_data',
        'device_id',
    ];
}
