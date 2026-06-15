<?php

use App\Http\Controllers\Api\V1\Migration\MigrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set', 'role:manager_of_reps,manager_of_raters'])
    ->prefix('migration')
    ->name('migration.')
    ->group(function () {
        Route::post('run-all', [MigrationController::class, 'runAll'])->name('run-all');
        Route::post('users', [MigrationController::class, 'runUsers'])->name('users');
        Route::post('external-users', [MigrationController::class, 'runExternalUsers'])->name('external-users');
        Route::post('requests', [MigrationController::class, 'runRequests'])->name('requests');
        Route::post('connections', [MigrationController::class, 'runConnections'])->name('connections');
        Route::post('ratings', [MigrationController::class, 'runRatings'])->name('ratings');
        Route::post('notifications', [MigrationController::class, 'runNotifications'])->name('notifications');
    });
