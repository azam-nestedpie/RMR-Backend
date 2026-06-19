<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberNetworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uid' => $this->firebase_uid,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'image_url' => $this->image_url,
            'company_name' => $this->company_name,
            'position' => $this->position,

        ];
    }
}
