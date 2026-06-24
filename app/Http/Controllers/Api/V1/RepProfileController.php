<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rep\ShowRepProfileRequest;
use App\Http\Resources\Api\V1\RepProfileResource;
use App\Services\V1\RepProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RepProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RepProfileService $repProfile,
    ) {}

    public function show(ShowRepProfileRequest $request, string $repUid): JsonResponse
    {
        $result = $this->repProfile->show($request->user(), $repUid);

        return (new RepProfileResource(
            $result['rep'],
            $result['avg_rating'],
            $result['connection_status'],
            $result['ratings'],
        ))->additional(['success' => true])->response();
    }
}
