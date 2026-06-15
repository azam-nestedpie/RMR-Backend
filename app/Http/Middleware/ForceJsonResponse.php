<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->isJson() && $request->getContent() !== '') {
            json_decode($request->getContent());

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON format.',
                    'errors' => [
                        'json' => [json_last_error_msg()],
                    ],
                ], 400);
            }
        }

        return $next($request);
    }
}
