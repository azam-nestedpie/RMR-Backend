<?php

use App\Http\Controllers\Api\V1\RepProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('reps')->name('reps.')->group(function () {
    Route::get('{repUid}', [RepProfileController::class, 'show'])->name('show');
});
