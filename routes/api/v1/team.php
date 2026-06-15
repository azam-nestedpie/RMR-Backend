<?php

use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'members'])->name('members');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::post('search', [TeamController::class, 'searchForInvite'])->name('search');
    Route::get('requests', [TeamController::class, 'requests'])->name('requests.index');
    Route::get('pending', [TeamController::class, 'pending'])->name('pending');
    Route::delete('leave/{managerUid}', [TeamController::class, 'leave'])->name('leave');
    Route::delete('{memberUid}', [TeamController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth:sanctum', 'password.set'])->prefix('team-requests')->name('team-requests.')->group(function () {
    Route::post('accept', [TeamController::class, 'accept'])->name('accept');
    Route::post('reject', [TeamController::class, 'reject'])->name('reject');
    Route::post('cancel', [TeamController::class, 'cancel'])->name('cancel');
});
