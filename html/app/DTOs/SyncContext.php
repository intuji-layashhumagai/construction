<?php

namespace App\DTOs;

use App\Models\SyncSession;
use Carbon\Carbon;

/**
 * Central DTO for Sync Operations
 * SyncContext serves as the single source of truth for all sync operation state,
 * eliminating scattered parameters and ensuring consistent data flow between Action classes.
 * Transforms complex sync operations from scattered state management into a clean,
 * maintainable data pipeline where each Action receives and potentially modifies
 * the same context object.
 */
class SyncContext
{
    public function __construct(
        public string $sessionId,
        public string $deviceId,
        public string $workerId,
        public SyncSession $sessionModel,
        public array $processedEvents = [],
        public ?Carbon $serverReceivedAt = null
    ) {}

    public static function create(string $sessionId, string $deviceId, string $workerId): self
    {
        $sessionModel = SyncSession::find($sessionId);

        return new self(
            sessionId: $sessionId,
            deviceId: $deviceId,
            workerId: $workerId,
            sessionModel: $sessionModel,
            serverReceivedAt: now()
        );
    }
}
