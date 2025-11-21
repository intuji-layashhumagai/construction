<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterDeviceRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth_service) {}

    /**
     * Display a listing of the resource.
     */
    public function login(LoginRequest $request)
    {
        return $this->auth_service->login($request->validated());
    }

    public function registerDevice(RegisterDeviceRequest $request)
    {
        return $this->auth_service->registerDevice($request->validated());
    }
}
