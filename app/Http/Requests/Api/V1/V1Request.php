<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class V1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
