<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'firebase_uid' => $this->firebase_uid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'bio' => $this->bio,
            'image_url' => $this->image_url,
            'company_name' => $this->company_name,
            'position' => $this->position,
            'fcm_token' => $this->fcm_token,
            'prefered_locale' => $this->prefered_locale,
            'is_blocked' => $this->is_blocked,
            'is_deleted' => $this->is_deleted,

            'role' => $this->relationLoaded('roles')
                ? new RoleResource($this->roles->first())
                : null,
            // 'roles' => $this->relationLoaded('roles')
            //     ? RoleResource::collection($this->roles)
            //     : null,

            'address' => $this->relationLoaded('address')
                ? new AddressResource($this->address)
                : null,

            'industry' => $this->relationLoaded('industries')
                ? new IndustryResource($this->industries->first())
                : null,
            // 'industries' => $this->relationLoaded('industries')
            //     ? IndustryResource::collection($this->industries)
            //     : null,
        ];

        if ($this->relationLoaded('salesRepProfile') && $this->salesRepProfile) {
            $data['avg_rating'] = $this->salesRepProfile->avg_rating;
            $data['ratings_count'] = $this->salesRepProfile->ratings_count;
        }

        return $data;
    }
}
