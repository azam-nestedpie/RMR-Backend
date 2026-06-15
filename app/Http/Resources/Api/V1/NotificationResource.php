<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uuid,
            'image_url' => $this->sender?->image_url,
            'full_name' => trim(($this->sender?->first_name ?? '').' '.($this->sender?->last_name ?? '')),
            'message' => $this->message,
            'screen' => $this->screen,
            'tab_index' => $this->tab_index,
            'is_read' => $this->is_read,
            'sent_at' => $this->sent_at,
            'read_at' => $this->read_at,
        ];
    }
}
