<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    require __DIR__.'/v1/roles.php';
    require __DIR__.'/v1/industry.php';
    require __DIR__.'/v1/auth.php';
    require __DIR__.'/v1/users.php';
    require __DIR__.'/v1/favorites.php';
    require __DIR__.'/v1/connections.php';
    require __DIR__.'/v1/team.php';
    require __DIR__.'/v1/ratings.php';
    require __DIR__.'/v1/dashboard.php';
    require __DIR__.'/v1/external.php';
    require __DIR__.'/v1/notifications.php';
    require __DIR__.'/v1/migration.php';
    require __DIR__.'/v1/upload.php';
    require __DIR__.'/v1/reps.php';
    require __DIR__.'/v1/raters.php';
});
