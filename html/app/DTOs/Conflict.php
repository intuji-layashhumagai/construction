<?php

namespace App\DTOs;

use App\Enums\ConflictType;

class Conflict
{
    public ConflictType $type;

    public SyncItem $local;

    public SyncItem $remote;

    public ResolutionStrategy $resolution;

    public ?array $metadata = null;

    public function __construct(array $data)
    {
        $this->type = $data['type'];
        $this->local = $data['local'];
        $this->remote = $data['remote'];
        $this->resolution = $data['resolution'] ?? ResolutionStrategy::LATEST_WINS;
        $this->metadata = $data['metadata'] ?? null;
    }
}
