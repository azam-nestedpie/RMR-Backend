<?php

use App\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index'])->name('index');
