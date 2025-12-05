<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RuleSyncController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\CertificateAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to the API']);
});

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register-device', [AuthController::class, 'registerDevice'])->name('registerDevice');

});

Route::middleware(CertificateAuthMiddleware::class)->group(function () {
    // Single intelligent sync endpoint - handles new sync, resume, and validation
    Route::post('/sync', [SyncController::class, 'intelligentSync'])->name('sync');

    // Check sync status for async operations
    Route::get('/sync/{sessionId}/status', [SyncController::class, 'checkSyncStatus'])->name('sync.status');
});
