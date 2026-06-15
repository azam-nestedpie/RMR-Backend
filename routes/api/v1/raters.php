<?php

use App\Http\Controllers\Api\V1\RaterProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('raters')->name('raters.')->group(function () {
    Route::get('{raterUid}', [RaterProfileController::class, 'show'])->name('show');
});
