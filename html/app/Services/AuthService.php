<?php

namespace App\Services;

use App\Actions\Auth\GenerateCertificateAction;
use App\Actions\Auth\GenerateVectorClock;
use App\Actions\Auth\StoreDeviceAction;
use App\Actions\Worker\GetSingleWorkerAction;
use App\Models\WorkerDevice;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function login(array $request)
    {
        $worker = GetSingleWorkerAction::handle($request['email']);
        if (! $worker || ! Hash::check($request['password'], $worker->password)) {

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $certificates = GenerateCertificateAction::handle($worker);
        $vectorClock = GenerateVectorClock::handle();

        WorkerDevice::create([
            'worker_id' => $worker->id,
            'device_id' => $request['deviceId'],
            'login_at' => now(),
            'last_vc_sent' => now(),
            'certificate_serial' => $certificates['serial_number'],
            'certificate_issue_time' => $certificates['time'],
        ]);

        return array_merge($certificates, ['vc' => $vectorClock]);
    }

    public function registerDevice(array $request)
    {
        return StoreDeviceAction::handle($request);
    }
}
