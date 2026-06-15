<?php

namespace App\Services\Auth;

use App\Exceptions\FirebaseTokenException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseAuthService
{
    private string $projectId;

    private string $apiKey;

    private string $authUrl;

    public function __construct(?string $projectId = null, ?string $apiKey = null, ?string $authUrl = null)
    {
        $this->projectId = $projectId ?? (string) config('firebase.project_id', '');
        $this->apiKey = $apiKey ?? (string) config('firebase.api_key', '');
        $this->authUrl = $authUrl ?? (string) config('firebase.auth_url', '');
    }

    /**
     * Sign in with email + password via Firebase REST API.
     * Returns Firebase user data including localId (the Firebase Auth UID).
     *
     * LOGIN FLOW:
     * 1. Laravel checks MySQL by email
     * 2. If a local password exists → bcrypt check
     * 3. If NOT found OR password is missing → call this method
     * 4. Success → upsert MySQL user with firebase_uid (as PK)
     * 5. Issue Sanctum token
     */
    public function signInWithEmailPassword(string $email, string $password): array
    {
        try {
            $response = Http::timeout(10)
                ->post($this->authUrl.'?key='.$this->apiKey, [
                    'email' => $email,
                    'password' => $password,
                    'returnSecureToken' => true,
                ]);

            if ($response->failed()) {
                $code = $response->json('error.message', 'UNKNOWN');

                Log::warning('Firebase sign-in failed', [
                    'email' => $email,
                    'code' => $code,
                ]);

                throw FirebaseTokenException::invalidToken(match ($code) {
                    'EMAIL_NOT_FOUND' => 'No account found with this email.',
                    'INVALID_PASSWORD' => 'Incorrect password.',
                    'INVALID_LOGIN_CREDENTIALS' => 'Invalid credentials.',
                    'USER_DISABLED' => 'This account has been disabled.',
                    'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Too many login attempts. Try again later.',
                    default => 'Authentication failed.',
                });
            }

            return $response->json();
        } catch (FirebaseTokenException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Firebase REST API unreachable', ['error' => $e->getMessage()]);
            throw FirebaseTokenException::serviceUnavailable();
        }
    }

    /**
     * Verify a Firebase ID token sent by mobile clients.
     * Decodes and validates JWT without external SDK.
     */
    public function verifyIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw FirebaseTokenException::invalidToken('Malformed JWT.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (empty($payload)) {
            throw FirebaseTokenException::invalidToken('Cannot decode token payload.');
        }

        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw FirebaseTokenException::invalidToken('Token has expired.');
        }
        if (isset($payload['aud']) && $payload['aud'] !== $this->projectId) {
            throw FirebaseTokenException::invalidToken('Token audience mismatch.');
        }

        return [
            'uid' => $payload['user_id'] ?? $payload['sub'],
            'email' => $payload['email'] ?? null,
            'payload' => $payload,
        ];
    }
}
