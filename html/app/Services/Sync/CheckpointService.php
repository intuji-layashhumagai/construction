<?php

namespace App\Services\Sync;

use App\DTOs\Checkpoint;
use Illuminate\Support\Facades\Cache;

class CheckpointService
{
    public function save(Checkpoint $checkpoint): void
    {
        $data = $checkpoint->toArray();
        $data['integrity_hash'] = $this->calculateHash($checkpoint);
        Cache::put("checkpoint:{$checkpoint->checkpointId}", $data, 3600);
    }

    public function load(string $checkpointId): ?Checkpoint
    {
        $data = Cache::get("checkpoint:{$checkpointId}");
        if (! $data) {
            return null;
        }

        // Verify integrity
        $originalHash = $data['integrity_hash'];
        unset($data['integrity_hash']);
        if (hash('sha256', json_encode($data)) !== $originalHash) {
            throw new \Exception("Checkpoint {$checkpointId} integrity check failed");
        }

        return Checkpoint::fromArray($data);
    }

    private function calculateHash(Checkpoint $checkpoint): string
    {
        $data = $checkpoint->toArray();
        unset($data['integrityHash']); // Avoid circular reference

        return hash('sha256', json_encode($data));
    }

    public function markComplete(Checkpoint $checkpoint): void
    {
        $this->save($checkpoint);
    }
}
