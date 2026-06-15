<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'token' => $this->resource['token'] ?? null,

            // LOGIN RESPONSE

            // REGISTER RESPONSE
            'firebase_uid' => $this->resource['firebase_uid'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'role' => $this->resource['role'] ?? null,
            'industry' => $this->resource['industry'] ?? null,
        ];
    }
}
