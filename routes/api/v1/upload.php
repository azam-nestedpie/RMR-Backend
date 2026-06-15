<?php

use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('upload')->name('upload.')->group(function () {
    Route::post('image', [UploadController::class, 'image'])->name('image');
});
