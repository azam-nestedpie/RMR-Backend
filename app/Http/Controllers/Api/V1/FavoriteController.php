<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConnectableUserResource;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $with = ['roles', 'address', 'industries'];

        if ($request->user()->hasRole(Role::RATER)) {
            $with[] = 'salesRepProfile';
        }

        $favorites = $request->user()
            ->favoriteUsers()
            ->with($with)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->paginate(20);

        return ConnectableUserResource::collection($favorites)
            ->additional(['success' => true])
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'firebase_uid' => ['required', 'string', 'exists:users,firebase_uid'],
        ]);

        $user = User::query()->where('firebase_uid', $request->input('firebase_uid'))->first();

        if (! $user) {
            throw new ApiException('User not found: '.$request->input('firebase_uid'), 404);
        }

        if ($request->user()->is($user)) {
            return $this->error('You cannot favorite yourself.');
        }

        $request->user()->favoriteUsers()->syncWithoutDetaching([
            $user->firebase_uid => [
                'created_by' => $request->user()->firebase_uid,
                'updated_by' => $request->user()->firebase_uid,
            ],
        ]);

        $user->load('salesRepProfile');

        $data = [
            'firebase_uid' => $user->firebase_uid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'image_url' => $user->image_url,
            'company_name' => $user->company_name,
            'position' => $user->position,
        ];

        // Show only for rater login
        if ($request->user()->hasRole(Role::RATER)) {
            $data['average_rating'] = $user->salesRepProfile?->avg_rating;
            $data['ratings_count'] = $user->salesRepProfile?->ratings_count;
        }

        return $this->success($data, 'User added to favorites.', 201);
    }

    public function destroy(Request $request, string $firebaseUid): JsonResponse
    {
        $request->user()->favoriteUsers()->detach($firebaseUid);

        return $this->success(null, 'User removed from favorites.');
    }
}
