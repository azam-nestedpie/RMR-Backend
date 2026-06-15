<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;

class ReceivedRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
