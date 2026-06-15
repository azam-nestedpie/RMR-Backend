<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rating\ExternalStoreRequest;
use App\Services\V1\RatingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ExternalRatingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RatingService $ratings,
    ) {}

    public function store(ExternalStoreRequest $request): JsonResponse
    {
        return $this->created(
            $this->ratings->submitExternal($request->validated()),
            'Rating submitted successfully.'
        );
    }
}
