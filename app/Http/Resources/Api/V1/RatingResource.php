<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $currentRoleId = $currentUser?->roles?->first()?->id;

        $user = match ($currentRoleId) {
            Role::RATER => $this->rep,
            Role::REPRESENTATIVE => $this->rater,
            default => null,
        };

        if (! $user) {
            return [];
        }

        $user->loadMissing('salesRepProfile');

        $isFavourite = DB::table('user_favorites')
            ->where('user_firebase_uid', $currentUser->firebase_uid)
            ->where('favorite_user_firebase_uid', $user->firebase_uid)
            ->exists();

        $data = [
            // 'rating_uuid' => $this->firebase_uuid,
            // 'average_score' => $this->average_score,
            // 'rated_at' => $this->rated_at?->toDateTimeString(),
            'firebase_uid' => $user->firebase_uid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'image_url' => $user->image_url,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'is_favourite' => (int) $isFavourite,
        ];

        if ($user->relationLoaded('salesRepProfile') && $user->salesRepProfile) {
            $data['avg_rating'] = $user->salesRepProfile->avg_rating;
            $data['ratings_count'] = $user->salesRepProfile->ratings_count;
        }

        return $data;
    }
}
