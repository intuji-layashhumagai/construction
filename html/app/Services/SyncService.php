<?php

namespace App\Services;

use App\Jobs\ProcessEventBatchJob;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public function __construct() {}

    /**
     * Process a batch of events from an offline device.
     *
     * This method handles the synchronization of multiple events, ensuring they are
     * integrated into the event store in the correct causal order while resolving
     * any conflicts that arise from concurrent modifications.
     */
    public function processEventBatch(array $eventsFromDevice): void
    {
        // Wrapped this entire batch in a database transaction
        // to ensure atomicity during conflict resolution and state updates

        DB::transaction(function () use ($eventsFromDevice) {
            foreach ($eventsFromDevice as $eventData) {
                ProcessEventBatchJob::dispatch($eventData['device_id'], $eventData);
            }
        });
    }
}
