<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    if (! Route::has('testing.logging.success')) {
        Route::middleware('api')->post('/api/testing/logging', function () {
            return response()->json(['success' => true]);
        })->name('testing.logging.success');
    }

    if (! Route::has('testing.logging.failure')) {
        Route::middleware('api')->get('/api/testing/logging/failure', function () {
            throw new RuntimeException('Logging failure test.');
        })->name('testing.logging.failure');
    }
});

test('api requests are logged with request id and redacted payload', function () {
    Log::spy();

    $this->postJson('/api/testing/logging', [
        'email' => 'client@example.com',
        'password' => 'secret',
        'nested' => [
            'token' => 'private-token',
            'safe_value' => 'visible',
        ],
    ])
        ->assertOk()
        ->assertHeader('X-Request-Id');

    Log::shouldHaveReceived('info')
        ->once()
        ->with('API request completed', Mockery::on(function (array $context): bool {
            return filled($context['request_id'] ?? null)
                && ($context['method'] ?? null) === 'POST'
                && ($context['path'] ?? null) === 'api/testing/logging'
                && ($context['status'] ?? null) === 200
                && ($context['payload']['email'] ?? null) === 'client@example.com'
                && ($context['payload']['password'] ?? null) === '[redacted]'
                && ($context['payload']['nested']['token'] ?? null) === '[redacted]'
                && ($context['payload']['nested']['safe_value'] ?? null) === 'visible';
        }));
});

test('api exceptions are logged and rendered with request id', function () {
    Log::spy();

    $this->getJson('/api/testing/logging/failure')
        ->assertStatus(500)
        ->assertJsonPath('success', false);

    Log::shouldHaveReceived('error')
        ->with('API request failed', Mockery::on(function (array $context): bool {
            return filled($context['request_id'] ?? null)
                && ($context['method'] ?? null) === 'GET'
                && ($context['path'] ?? null) === 'api/testing/logging/failure'
                && ($context['status'] ?? null) === 500;
        }));
});
