<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Services\EventService;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

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
}
