<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleVersion extends Model
{
    use HasUuids;

    protected $fillable = ['rule_id', 'version', 'rule_definition', 'created_by'];

    protected function casts(): array
    {
        return [
            'rule_definition' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'created_by');
    }
}
