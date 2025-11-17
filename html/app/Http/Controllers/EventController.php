<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Models\EventStore;
use App\Services\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request)
    {

        return $this->eventService->storeEvent($request->validated());
    }

    /**
     * Display the specified resource.
     */
    public function show(EventStore $eventStore)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EventStore $eventStore)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EventStore $eventStore)
    {
        //
    }
}
