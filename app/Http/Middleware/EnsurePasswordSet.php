<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            Log::warning('Password gate failed: unauthenticated request', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // If password is null, user hasn't set one yet (migrated user)
        if (is_null($user->password)) {
            Log::info('Password gate blocked user without password', [
                'user_uid' => $user->firebase_uid,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please set your password before continuing.',
                'requires_action' => 'set_password',
                'action_url' => '/api/v1/auth/set-password',
            ], 403);
        }

        Log::info('Password gate passed', [
            'user_uid' => $user->firebase_uid,
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
