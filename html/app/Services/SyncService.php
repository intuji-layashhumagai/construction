<?php

namespace App\Services;

use App\Jobs\ProcessEventBatchJob;

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
        if (empty($eventsFromDevice)) {
            return;
        }

        $deviceId = $eventsFromDevice[0]['device_id'];

        ProcessEventBatchJob::dispatch($deviceId, $eventsFromDevice);
    }
}
