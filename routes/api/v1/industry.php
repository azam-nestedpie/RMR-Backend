<?php

use App\Http\Controllers\Api\V1\IndustryController;
use Illuminate\Support\Facades\Route;

Route::get('industries', [IndustryController::class, 'index'])
    ->name('industries.index');
