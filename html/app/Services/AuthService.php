<?php

namespace App\Services;

use App\Actions\Auth\GenerateCertificateAction;
use App\Actions\Auth\GenerateOfflineContextAction;
use App\Actions\Auth\GenerateVectorClock;
use App\Actions\Auth\StoreDeviceAction;
use App\Actions\Worker\GetSingleWorkerAction;
use App\Enums\EventType;
use App\Models\Event;
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

        // Generate vector clock based on worker's session history
        $vectorClock = GenerateVectorClock::handle($worker->id, $request['deviceId']);

        // Get cached offline context (shared across all devices)
        $offlineContext = GenerateOfflineContextAction::getCached();

        // Create WorkerDevice record
        WorkerDevice::create([
            'worker_id' => $worker->id,
            'device_id' => $request['deviceId'],
            'login_at' => now(),
            'last_vc_sent' => now(),
            'certificate_serial' => $certificates['serial_number'],
            'certificate_issue_time' => $certificates['time'],
        ]);

        // Create login event in the event store
        $sequenceNumber = GenerateVectorClock::getNextSequenceNumber($worker->id);

        Event::create([
            'entity_type' => 'worker_session',
            'entity_id' => $worker->id,
            'event_type' => EventType::WORKER_LOGGED_IN->value,
            'event_data' => [
                'device_id' => $request['deviceId'],
                'login_at' => now()->toISOString(),
                'ip_address' => $request['ip'] ?? null,
                'user_agent' => $request['user_agent'] ?? null,
            ],
            'worker_id' => $worker->id,
            'device_id' => $request['deviceId'],
            'vector_clock' => $vectorClock,
            'sequence_number' => $sequenceNumber,
            'server_created_at' => now(),
        ]);

        return array_merge($certificates, [
            'vector_clock' => $vectorClock,
            'worker_id' => $worker->id,
            'offline_context' => $offlineContext,
        ]);
    }

    public function registerDevice(array $request)
    {
        return StoreDeviceAction::handle($request);
    }
}
