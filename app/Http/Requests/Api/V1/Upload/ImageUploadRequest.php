<?php

namespace App\Http\Requests\Api\V1\Upload;

use App\Http\Requests\Api\V1\V1Request;

class ImageUploadRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
