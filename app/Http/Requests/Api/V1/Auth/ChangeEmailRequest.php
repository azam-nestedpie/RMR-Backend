<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;
use Illuminate\Support\Facades\Hash;

class ChangeEmailRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'new_email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
        ];
    }
}
