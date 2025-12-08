<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterDeviceRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Display a listing of the resource.
     */
    public function login(LoginRequest $request)
    {
        return $this->authService->login($request->validated());
    }

    public function registerDevice(RegisterDeviceRequest $request)
    {
        return $this->authService->registerDevice($request->validated());
    }
}
