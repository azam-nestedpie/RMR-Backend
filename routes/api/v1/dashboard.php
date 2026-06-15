<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set', 'role:manager_of_reps,manager_of_raters'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::post('export-email', [DashboardController::class, 'exportEmail'])->name('export-email');
        Route::get('home', [DashboardController::class, 'home'])->name('home');
    });

Route::middleware(['auth:sanctum', 'password.set', 'role:rater'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        Route::get('rater-home', [DashboardController::class, 'raterHome'])->name('rater-home');
    });

Route::middleware(['auth:sanctum', 'password.set', 'role:rep'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        Route::get('rep-home', [DashboardController::class, 'repHome'])->name('rep-home');
    });
