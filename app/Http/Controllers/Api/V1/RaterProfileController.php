<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rater\ShowRaterProfileRequest;
use App\Http\Resources\Api\V1\RaterProfileResource;
use App\Services\V1\RaterProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RaterProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RaterProfileService $raterProfile,
    ) {}

    public function show(ShowRaterProfileRequest $request, string $raterUid): JsonResponse
    {
        $result = $this->raterProfile->show($request->user(), $raterUid);

        return (new RaterProfileResource(
            $result['rater'],
            $result['connection_status'],
            $result['ratings'],
        ))->additional(['success' => true])->response();
    }
}
