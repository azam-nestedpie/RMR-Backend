<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequest
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'firebase_token',
        'id_token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        try {
            $response = $next($request);

            $this->logResponse($request, $response, $requestId, $startedAt);

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        } catch (Throwable $exception) {
            Log::error('API request failed', array_merge(
                $this->baseContext($request, $requestId, $startedAt),
                [
                    'duration_ms' => $this->durationInMilliseconds($startedAt),
                    'exception' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ],
            ));

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(Request $request, string $requestId, float $startedAt): array
    {
        return [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
            'user_uid' => $request->user()?->firebase_uid,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query' => $this->redactSensitiveValues($request->query()),
            'payload' => $this->payloadForLogging($request),
            'started_at' => now()->subMilliseconds($this->durationInMilliseconds($startedAt))->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForLogging(Request $request): array
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return [];
        }

        return $this->redactSensitiveValues($request->except(array_keys($request->allFiles())));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redactSensitiveValues(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value)
                ? $this->redactSensitiveValues($value)
                : $value;
        }

        return $redacted;
    }

    private function durationInMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function logResponse(Request $request, Response $response, string $requestId, float $startedAt): void
    {
        $context = array_merge(
            $this->baseContext($request, $requestId, $startedAt),
            [
                'status' => $response->getStatusCode(),
                'duration_ms' => $this->durationInMilliseconds($startedAt),
            ],
        );

        if ($response->getStatusCode() >= 500) {
            Log::error('API request failed', $context);

            return;
        }

        if ($response->getStatusCode() >= 400) {
            Log::warning('API request rejected', $context);

            return;
        }

        Log::info('API request completed', $context);
    }
}
