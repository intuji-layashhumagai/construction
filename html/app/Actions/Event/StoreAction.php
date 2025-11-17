<?php

namespace App\Actions\Event;
use App\Models\EventStore;

final class StoreAction 
{ 
    public static function handle(array $request) 
    {

       return EventStore::create($request);
    } 
}