<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Upload\ImageUploadRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    use ApiResponseTrait;

    public function image(ImageUploadRequest $request): JsonResponse
    {
        $path = $request->file('image')->store('profile_images', 'public');

        $url = Storage::url($path);

        return $this->created(['image_url' => $url], 'Image uploaded successfully.');
    }
}
