<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FirebaseTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangeEmailRequest;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\SetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Services\V1\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password')
            );
        } catch (FirebaseTokenException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        $response = (new UserResource($result['user']))->resolve();
        $response['token'] = $result['token'];

        return $this->success($response, 'Login successful.');
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $this->authService->register($request->validated());

        $response = (new UserResource($payload['user']))->resolve();
        $response['token'] = $payload['token'];

        return $this->success($response, 'Account created successfully.', 201);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email'),
            function (object $notifiable, string $token) {
                $notifiable->notify(new ResetPasswordNotification($token));
            }
        );

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, 'Reset link sent to your email.')
            : $this->error('Unable to send reset link.', 400);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (object $user, string $password) {
                $user->forceFill([
                    'password' => bcrypt($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, 'Password has been reset.')
            : $this->error('Invalid or expired reset token.', 400);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/v1/auth/set-password
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->setPassword($request->user(), $request->validated('password'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return (new UserResource($user))
            ->additional(['success' => true, 'message' => 'Password set successfully.'])
            ->response();
    }

    /**
     * POST /api/v1/auth/change-email
     */
    public function changeEmail(ChangeEmailRequest $request): JsonResponse
    {
        $this->authService->changeEmail(
            $request->user(),
            $request->validated('new_email')
        );

        return $this->success(null, 'Email changed successfully.');
    }

    /**
     * POST /api/v1/auth/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('new_password')
        );

        return $this->success(null, 'Password changed successfully.');
    }
}
