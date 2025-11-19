<?php

namespace App\Services;

use App\Actions\Event\ProcessSingleEventAction;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public function __construct() {}

    // todo: implementation of queue of background sync
    /**
     * Process a batch of events from an offline device.
     *
     * This method handles the synchronization of multiple events, ensuring they are
     * integrated into the event store in the correct causal order while resolving
     * any conflicts that arise from concurrent modifications.
     */
    public function processEventBatch(array $eventsFromDevice): void
    {
        // Wraped this entire batch in a database transaction
        // to ensure atomicity during conflict resolution and state updates

        DB::transaction(function () use ($eventsFromDevice) {
            foreach ($eventsFromDevice as $eventData) {
                ProcessSingleEventAction::handle($eventData);
            }
        });
    }
}
