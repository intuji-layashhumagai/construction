<?php

namespace App\Actions\Sync;

use App\Models\SyncSession;

final class GetSingleSessionAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(string $sessionId): SyncSession
    {
        return SyncSession::where('id', $sessionId)->first() ?? null;
    }
}
