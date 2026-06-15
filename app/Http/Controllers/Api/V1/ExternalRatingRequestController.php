<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExternalRatingRequest\StoreRequest;
use App\Http\Requests\Api\V1\ExternalRatingRequest\SubmitRequest;
use App\Http\Resources\Api\V1\RatingQuestionResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\ExternalRatingRequest;
use App\Services\V1\ExternalRatingRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalRatingRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ExternalRatingRequestService $externalRatingRequests,
    ) {}

    public function store(StoreRequest $request): JsonResponse
    {
        $invitation = $this->externalRatingRequests->sendInvitation(
            $request->user(),
            $request->validated()
        );

        return $this->created(
            [
                'invite_uuid' => $invitation->invite_uuid,
                'email' => $invitation->email,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
            ],
            'Invitation sent successfully.'
        );
    }

    public function show(Request $request, ExternalRatingRequest $externalRatingRequest): JsonResponse
    {
        $payload = $this->externalRatingRequests->show($externalRatingRequest);

        return $this->success(
            [
                'sales_rep' => (new UserResource($payload['sales_rep']))->resolve($request),
                'rating_questions' => RatingQuestionResource::collection($payload['rating_questions'])->resolve($request),
            ]
        );
    }

    public function submit(SubmitRequest $request, ExternalRatingRequest $externalRatingRequest): JsonResponse
    {
        $payload = $this->externalRatingRequests->submit(
            $externalRatingRequest,
            $request->validated()
        );

        return $this->success(
            $payload,
            'External rating submitted successfully.'
        );
    }
}
