<?php

namespace App\Http\Requests\Api\V1\Rep;

use App\Http\Requests\Api\V1\V1Request;
use App\Models\Role;

class ShowRepProfileRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::RATER) === true;
    }

    public function rules(): array
    {
        return [
            'repUid' => ['prohibited'],
        ];
    }
}
