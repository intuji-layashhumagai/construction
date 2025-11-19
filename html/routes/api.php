<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('events')->group(function () {
    // Store new event
    Route::post('/', [EventController::class, 'store'])->name('storeEvent');
    Route::post('/sync', [EventController::class, 'sync'])->name('syncEvent');

});
