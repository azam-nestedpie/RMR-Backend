<?php

use App\Http\Controllers\Api\V1\ExternalRatingRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('external-rating-requests')->name('external-rating-requests.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ExternalRatingRequestController::class, 'store'])
            ->middleware('throttle:external-rating-requests')
            ->name('store');
    });

    Route::get('{externalRatingRequest:invite_uuid}', [ExternalRatingRequestController::class, 'show'])
        ->name('show');

    Route::post('{externalRatingRequest:invite_uuid}/submit', [ExternalRatingRequestController::class, 'submit'])
        ->middleware('throttle:external-rating-submissions')
        ->name('submit');
});
