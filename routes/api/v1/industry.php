<?php

use App\Http\Controllers\Api\V1\IndustryController;
use Illuminate\Support\Facades\Route;

Route::get('industries', [IndustryController::class, 'index'])
    ->name('industries.index');

Route::get('industries/{industry}/rating-questions', [IndustryController::class, 'ratingQuestions'])
    ->name('industries.rating-questions');
