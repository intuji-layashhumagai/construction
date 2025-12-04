<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'version', 'entity_type', 'event_type',
        'conditions', 'actions', 'priority', 'is_active',
        'effective_from', 'effective_until', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'priority' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RuleVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'created_by');
    }

    public function deviceRules(): HasMany
    {
        return $this->hasMany(DeviceRule::class);
    }

    // Scope for active rules within effective date range
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            });
    }
}
