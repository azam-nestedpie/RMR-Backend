<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $requiredRoles = array_values(array_unique($roles));

        if (! $user) {
            Log::warning('Role check failed: unauthenticated request', [
                'path' => $request->path(),
                'required_roles' => $requiredRoles,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $userRoles = $user->roleNames();

        if (! $user->hasRole($requiredRoles)) {
            Log::warning('Role check failed: forbidden', [
                'user_uid' => $user->firebase_uid,
                'path' => $request->path(),
                'required_roles' => $requiredRoles,
                'user_roles' => $userRoles,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
                'error' => 'FORBIDDEN',
                'required_roles' => $requiredRoles,
                'your_roles' => $userRoles,
            ], 403);
        }

        Log::info('Role check passed', [
            'user_uid' => $user->firebase_uid,
            'path' => $request->path(),
            'roles' => $userRoles,
            'required_roles' => $requiredRoles,
        ]);

        return $next($request);
    }
}
