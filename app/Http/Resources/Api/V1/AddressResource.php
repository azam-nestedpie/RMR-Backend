<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            // 'postal_code' => $this->postal_code,
            'address_line' => implode(', ', array_filter([
                $this->city,
                $this->state,
                $this->country,
            ])),
        ];
    }
}
