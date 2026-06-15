<?php

use App\Http\Controllers\Api\V1\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('connections')->name('connections.')->group(function () {
    Route::get('/', [ConnectionController::class, 'index'])->name('index');
    Route::get('requests', [ConnectionController::class, 'requests'])->name('requests.index');
    Route::get('requests/pending', [ConnectionController::class, 'pendingRequests'])->name('pending');
    Route::get('pending', [ConnectionController::class, 'pendingRequests'])->name('pending.alias');
    Route::post('request', [ConnectionController::class, 'sendRequest'])->name('request');
    Route::post('request/bulk', [ConnectionController::class, 'sendBulk'])->name('request.bulk');
    Route::post('request/on-behalf', [ConnectionController::class, 'sendRequestOnBehalf'])->name('request.on-behalf');
    Route::delete('{uuid}', [ConnectionController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth:sanctum', 'password.set'])->prefix('connection-requests')->name('connection-requests.')->group(function () {
    Route::post('accept', [ConnectionController::class, 'acceptRequest'])->name('accept');
    Route::post('reject', [ConnectionController::class, 'rejectRequest'])->name('reject');
    Route::post('cancel', [ConnectionController::class, 'cancelRequest'])->name('cancel');
});
