<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Services\EventService;
use App\Services\SyncService;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly SyncService $syncService) {}

    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request)
    {

        return $this->eventService->storeEvent($request->validated());
    }

    public function sync(StoreEventRequest $request)
    {

        return $this->syncService->processEventBatch($request->validated());
    }
}
