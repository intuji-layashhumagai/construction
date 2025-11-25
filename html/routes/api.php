<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\CertificateAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(CertificateAuthMiddleware::class)->prefix('events')->group(function () {
    // Store new event
    Route::post('/', [EventController::class, 'store'])->name('storeEvent');
    Route::post('/sync', [EventController::class, 'sync'])->name('syncEvent');

});

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register-device', [AuthController::class, 'registerDevice'])->name('registerDevice');

});

Route::middleware(CertificateAuthMiddleware::class)->prefix('sync')->group(function () {
    Route::post('/initiate', [SyncController::class, 'sync'])->name('sync');
    Route::post('/process/{sessionId}', [SyncController::class, 'syncProcess'])->name('syncProcess');
    Route::post('/resume/{sessionId}', [SyncController::class, 'syncResume'])->name('syncResume');
    Route::post('/schedule/{sessionId}', [SyncController::class, 'syncSchedule'])->name('syncSchedule');
});
