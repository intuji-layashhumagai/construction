<?php

namespace App\Services;

use App\Actions\Event\StoreAction as EventStoreAction;

class EventService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        
    }

    public function storeEvent(array $eventData)
    {
       return EventStoreAction::handle($eventData);
    }
}
