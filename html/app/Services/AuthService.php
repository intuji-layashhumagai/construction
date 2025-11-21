<?php

namespace App\Services;

use App\Actions\Auth\GenerateCertificateAction;
use App\Actions\Auth\GenerateVectorClock;
use App\Actions\Worker\GetSingleWorkerAction;
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

        return array_merge($certificates, ['vc' => $vectorClock]);
    }
}
