<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\SearchRequest;
use App\Http\Requests\Api\V1\User\UpdateLanguageRequest;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ConnectableUserResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\V1\LanguageService;
use App\Services\V1\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $users,
        private readonly LanguageService $languages,
    ) {}

    /** GET /api/v1/users/profile */
    public function profile(Request $request): JsonResponse
    {
        return (new UserResource($this->users->profile($request->user())))
            ->additional(['success' => true])
            ->response();

    }

    /** PUT /api/v1/users/profile */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $addressFields = Arr::only($validated, ['postal_code', 'address_line_1']);

        if (! empty($validated['address'])) {
            $parts = array_map('trim', explode(',', $validated['address'], 3));
            $addressFields['city'] = $parts[0] ?? null;
            $addressFields['state'] = $parts[1] ?? null;
            $addressFields['country'] = $parts[2] ?? null;
        }

        $user = $this->users->updateProfile(
            $request->user(),
            Arr::only($validated, ['first_name', 'last_name', 'bio', 'image_url', 'company_name', 'position', 'fcm_token', 'industry']),
            $addressFields,
        );

        return (new UserResource($user))
            ->additional(['success' => true, 'message' => 'Profile updated successfully.'])
            ->response();
    }

    /**
     * GET /api/v1/users/{userUid}
     */
    public function show(Request $request, string $userUid): JsonResponse
    {
        try {
            $currentRole = $request->user()->loadMissing('roles')->roles->first()?->name;

            $user = $this->users->show($userUid, $currentRole);

            if (! $user) {
                return $this->notFound('No User Found According To Your Search');
            }
            if ($user->is_blocked) {
                return $this->forbidden('This account is unavailable.');
            }

            return (new UserResource($user))->additional(['success' => true])->response();
        } catch (\Throwable $e) {
            Log::error('User show failed', [
                'error' => $e->getMessage(),
                'uid' => $request->user()?->firebase_uid,
                'target_uid' => $userUid,
            ]);

            return $this->error('An unexpected error occurred.', 500);
        }
    }

    /**
     * GET /api/v1/users/search?first_name=john&company_name=acme
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentRole = $user->loadMissing('roles')->roles->first()?->name;

            $results = $this->users->search(
                $request->validated(),
                $user->firebase_uid,
                $currentRole,
            );

            if ($results->total() === 0) {
                return $this->notFound('No User Found According To Your Search');
            }

            return ConnectableUserResource::collection($results)
                ->additional(['success' => true])
                ->response();
        } catch (\Throwable $e) {
            Log::error('User search failed', [
                'error' => $e->getMessage(),
                'uid' => $request->user()?->firebase_uid,
            ]);

            return $this->error('An unexpected error occurred.', 500);
        }
    }

    /**
     * GET /api/v1/users/me/connections
     */
    public function myConnections(Request $request): JsonResponse
    {
        return UserResource::collection($this->users->myConnections($request->user()->firebase_uid))
            ->additional(['success' => true])
            ->response();
    }

    /** PUT /api/v1/users/language */
    public function updateLanguage(UpdateLanguageRequest $request): JsonResponse
    {
        $user = $this->languages->updateLanguage($request->user(), $request->validated('locale'));

        return response()->json([

            'data' => [
                'firebase_uid' => $user->firebase_uid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'position' => $user->position,
                'company_name' => $user->company_name,
                'image_url' => $user->image_url,
                'prefered_locale' => $user->prefered_locale,
            ],
            'success' => true,
            'message' => 'Language updated successfully.',
        ]);
    }

    /** DELETE /api/v1/users/me */
    public function destroy(Request $request): JsonResponse
    {
        $this->users->destroy($request->user());

        return response()->json(['success' => true, 'message' => 'Account deleted successfully.']);
    }
}
