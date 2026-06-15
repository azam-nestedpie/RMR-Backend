<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('users')->name('users.')->group(function () {
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::put('profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::get('me/connections', [UserController::class, 'myConnections'])->name('my-connections');
    Route::delete('me', [UserController::class, 'destroy'])->name('destroy');
    Route::post('search', [UserController::class, 'search'])->name('search');
    Route::put('language', [UserController::class, 'updateLanguage'])->name('language.update');
    Route::get('{userUid}', [UserController::class, 'show'])->name('show');
});
