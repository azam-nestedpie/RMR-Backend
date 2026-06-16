<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePasswordSet;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            LogApiRequest::class,
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => CheckPermission::class,
            'password.set' => EnsurePasswordSet::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReportDuplicates();

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->dontReport([
            ApiException::class,
        ]);

        $exceptions->report(function (Throwable $e): void {
            if (! app()->resolved('request')) {
                return;
            }

            $request = request();

            if (! $request->is('api/*')) {
                return;
            }

            Log::error('API exception reported', [
                'request_id' => $request->attributes->get('request_id'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null,
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'route_action' => $request->route()?->getActionName(),
                'user_uid' => $request->user()?->firebase_uid,
                'ip' => $request->ip(),
            ]);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
            }
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Method not allowed.'], 405);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof ApiException) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
                }

                if ($e instanceof AuthorizationException) {
                    return response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
                }

                if ($e instanceof AccessDeniedHttpException) {
                    return response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
                }

                if ($e instanceof ModelNotFoundException) {
                    return response()->json(['success' => false, 'message' => 'Resource not found.'], 404);
                }

                $debug = config('app.debug');

                return response()->json([
                    'success' => false,
                    'message' => $debug ? $e->getMessage() : 'An unexpected error occurred.',
                    'trace' => $debug ? collect($e->getTrace())->take(5)->toArray() : null,
                ], 500);
            }
        });
    })
    ->create();
