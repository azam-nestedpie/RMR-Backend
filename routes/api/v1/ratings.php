<?php

use App\Http\Controllers\Api\V1\RatingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.set'])->prefix('ratings')->name('ratings.')->group(function () {
    Route::get('/', [RatingController::class, 'index'])->name('index');
    Route::get('user/{userUid}', [RatingController::class, 'forUser'])->name('user');
    Route::get('user/{userUid}/questions', [RatingController::class, 'userQuestions'])->name('user.questions');
    Route::get('user/{userUid}/average-by-question', [RatingController::class, 'averageByQuestion'])->name('average-by-question');
    Route::get('requests', [RatingController::class, 'requests'])->name('requests.index');
    Route::post('request/on-behalf', [RatingController::class, 'sendRequestOnBehalf'])->name('request.on-behalf');
    Route::get('pending', [RatingController::class, 'pendingRequests'])->name('pending');
    Route::get('team', [RatingController::class, 'teamRatings'])->name('team');
    Route::get('{ratingUuid}/items', [RatingController::class, 'items'])->name('items');
    Route::post('requests', [RatingController::class, 'sendRequest'])->name('requests.store');

    Route::middleware('permission:ratings.submit')->group(function () {
        Route::post('/', [RatingController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{ratingUuid}', [RatingController::class, 'update'])->name('update');
    });

});

Route::middleware(['auth:sanctum', 'password.set'])->prefix('rating-requests')->name('rating-requests.')->group(function () {
    Route::post('reject', [RatingController::class, 'rejectRequest'])->name('reject');
    Route::post('cancel', [RatingController::class, 'cancelRequest'])->name('cancel');
});
