<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols(),
                function ($attribute, $value, $fail) {
                    if (Hash::check($value, $this->user()->password)) {
                        $fail('The new password must be different from the current password.');
                    }
                },
            ],
        ];
    }
}
