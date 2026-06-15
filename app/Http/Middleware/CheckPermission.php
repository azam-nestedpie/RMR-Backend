<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Check if authenticated user has a specific permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            Log::warning('Permission check failed: unauthenticated request', [
                'path' => $request->path(),
                'required_permission' => $permission,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (! $user->can($permission)) {
            Log::warning('Permission check failed: forbidden', [
                'user_uid' => $user->firebase_uid,
                'path' => $request->path(),
                'required_permission' => $permission,
                'user_roles' => $user->roles()->pluck('name')->all(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'error' => 'FORBIDDEN',
                'required_permission' => $permission,
            ], 403);
        }

        Log::info('Permission check passed', [
            'user_uid' => $user->firebase_uid,
            'path' => $request->path(),
            'required_permission' => $permission,
        ]);

        return $next($request);
    }
}
