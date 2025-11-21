<?php

namespace App\Actions\Auth;

use App\Models\Device;

final class StoreDeviceAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(array $request)
    {
        return Device::create($request);
    }
}
