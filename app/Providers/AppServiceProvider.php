<?php

namespace App\Providers;

use App\Models\Permission;
use App\Repositories\ConnectionRepository;
use App\Repositories\Contracts\ConnectionRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\RatingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\DashboardRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RatingRepository;
use App\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ConnectionRepositoryInterface::class, ConnectionRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(RatingRepositoryInterface::class, RatingRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('external-rating-requests', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($request->user()?->firebase_uid ?? $request->ip()),
            ];
        });

        RateLimiter::for('external-rating-submissions', function (Request $request): array {
            return [
                Limit::perMinute(5)->by($request->ip()),
            ];
        });

        Gate::before(function ($user, string $ability) {
            if (! Permission::query()->where('permission', $ability)->exists()) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }
}
