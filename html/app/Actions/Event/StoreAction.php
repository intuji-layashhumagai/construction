<?php

namespace App\Actions\Event;

use App\Models\Event;

final class StoreAction
{
    public static function handle(array $request)
    {

        return Event::create($request);
    }
}
