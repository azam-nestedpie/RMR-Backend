<?php

use App\Http\Controllers\Api\V1\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->group(function () {
    Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::prefix('users')->name('users.')->group(function () {
        Route::post('favorite', [FavoriteController::class, 'store'])->name('favorite.store');
        Route::delete('{firebaseUid}/favorite', [FavoriteController::class, 'destroy'])->name('favorite.destroy');
    });
});
