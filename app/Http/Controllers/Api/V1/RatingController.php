<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Rating\ActionRequest;
use App\Http\Requests\Api\V1\Rating\ReceivedRequest;
use App\Http\Requests\Api\V1\Rating\SendOnBehalfRequest;
use App\Http\Requests\Api\V1\Rating\SendRequest;
use App\Http\Requests\Api\V1\Rating\StoreRequest;
use App\Http\Requests\Api\V1\Rating\UpdateRequest;
use App\Http\Resources\Api\V1\ConnectableUserResource;
use App\Http\Resources\Api\V1\RatingQuestionResource;
use App\Http\Resources\Api\V1\RatingRequestResource;
use App\Http\Resources\Api\V1\RatingResource;
use App\Models\Rating;
use App\Models\RatingQuestion;
use App\Models\RatingRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\V1\RatingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RatingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RatingService $ratings,
    ) {}

    /**
     * POST /api/v1/ratings
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $payload = $this->ratings->submit($request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Rating submitted successfully.', 'data' => $payload], 201);
    }

    public function update(UpdateRequest $request, string $ratingUuid): JsonResponse
    {
        $payload = $this->ratings->update($request->user(), $ratingUuid, $request->validated());

        return response()->json(['success' => true, 'message' => 'Rating updated successfully.', 'data' => $payload]);
    }

    public function items(Request $request, string $ratingUuid): JsonResponse
    {
        $rating = Rating::where('firebase_uuid', $ratingUuid)->first();

        if (! $rating) {
            return $this->notFound('Rating not found.');
        }

        $locale = $request->user()?->prefered_locale ?? 'en';

        $items = $this->ratings->ratingItems($ratingUuid, $locale);

        $isEditable = $rating->rated_at
            && $rating->rater_firebase_uid === $request->user()?->firebase_uid
            && $rating->rated_at->gte(now()->subHours(24));

        return $this->success([
            'is_editable' => (int) $isEditable,
            'rating_details' => $items,
        ], 'Rating details fetched successfully.');
    }

    /** POST /api/v1/ratings/requests */
    public function sendRequest(SendRequest $request): JsonResponse
    {
        $target = $this->ratings->findUserForAuthorization($request->validated('target_uid'));
        Gate::authorize('send', [RatingRequest::class, $target]);

        $payload = $this->ratings->sendRequest($request->user(), $request->validated('target_uid'));

        return response()->json(['success' => true, 'message' => 'Rating request sent.', 'data' => $payload], 201);
    }

    public function sendRequestOnBehalf(SendOnBehalfRequest $request): JsonResponse
    {
        $behalfUser = $this->ratings->findUserForAuthorization($request->validated('behalf_uid'));
        $target = $this->ratings->findUserForAuthorization($request->validated('target_uid'));
        Gate::authorize('sendOnBehalf', [RatingRequest::class, $behalfUser, $target]);

        $payload = $this->ratings->sendRequestOnBehalf(
            $request->user(),
            $request->validated('behalf_uid'),
            $request->validated('target_uid')
        );

        return response()->json(['success' => true, 'message' => 'Rating request sent.', 'data' => $payload], 201);
    }

    public function rejectRequest(ActionRequest $request): JsonResponse
    {
        $requestUuid = $request->input('request_uuid');
        $ratingRequest = $this->ratings->findRequestForAuthorization($requestUuid);
        Gate::authorize('reject', $ratingRequest);
        $this->ratings->rejectRequest($request->user(), $requestUuid);

        return response()->json(['success' => true, 'message' => 'Rating request rejected.']);
    }

    public function cancelRequest(ActionRequest $request): JsonResponse
    {
        $requestUuid = $request->input('request_uuid');
        $ratingRequest = $this->ratings->findRequestForAuthorization($requestUuid);
        Gate::authorize('cancel', $ratingRequest);
        $this->ratings->cancelRequest($request->user(), $requestUuid);

        return response()->json(['success' => true, 'message' => 'Rating request cancelled.']);
    }

    public function pendingRequests(Request $request): JsonResponse
    {
        return RatingRequestResource::collection($this->ratings->pendingRequests($request->user()->firebase_uid))
            ->additional(['success' => true])
            ->response();
    }

    public function requests(Request $request): JsonResponse
    {
        $requests = $this->ratings->requests($request->user());
        $data = collect();

        foreach (['received', 'sent'] as $direction) {
            $items = $requests[$direction] ?? collect();
            $data = $data->concat($items->map(function ($req) use ($request, $direction) {
                $user = match ($direction) {
                    'received' => $req->relationLoaded('requester') ? $req->requester : null,
                    'sent' => $req->relationLoaded('target') ? $req->target : null,
                };
                $user?->loadMissing('salesRepProfile');

                return array_merge(
                    $user ? (new ConnectableUserResource($user))->resolve($request) : [],
                    ['request_uuid' => $req->firebase_uuid, 'status' => $req->status?->name, 'direction' => $direction]
                );
            }));
        }

        return response()->json([
            'success' => true,
            'data' => $data->values()->toArray(),
        ]);
    }

    public function teamRatings(Request $request): JsonResponse
    {
        Gate::authorize('viewTeamRatings', RatingRequest::class);

        return RatingRequestResource::collection($this->ratings->teamRatings($request->user()))
            ->additional(['success' => true])
            ->response();
    }

    /** GET /api/v1/ratings/user/{userUid}/questions */
    public function userQuestions(Request $request, string $userUid): JsonResponse
    {
        $rep = User::where('firebase_uid', $userUid)->first();

        if (! $rep || ! $rep->hasRole(Role::REPRESENTATIVE)) {
            return $this->notFound('User not found.');
        }

        $industryIds = $rep->industries()->pluck('industries.id');

        $questions = RatingQuestion::whereHas('industries', fn ($q) => $q->whereIn('industries.id', $industryIds))
            ->where('is_active', true)
            ->distinct()
            ->orderBy('question_code')
            ->get();

        return RatingQuestionResource::collection($questions)
            ->additional(['success' => true])
            ->response();
    }

    /** GET /api/v1/ratings */
    public function index(ReceivedRequest $request): JsonResponse
    {
        $ratings = $this->ratings->forAuthenticatedUser($request->user(), $request->validated());

        return RatingResource::collection($ratings)->additional(['success' => true])->response();
    }

    /** GET /api/v1/ratings/user/{userUid} */
    public function forUser(Request $request, string $userUid): JsonResponse
    {
        return RatingResource::collection($this->ratings->forUser($userUid))
            ->additional(['success' => true])
            ->response();
    }

    /** GET /api/v1/ratings/user/{userUid}/average-by-question */
    public function averageByQuestion(Request $request, string $userUid): JsonResponse
    {
        $locale = $request->user()?->prefered_locale ?? 'en';

        return response()->json([
            'success' => true,
            'data' => [
                'firebase_uid' => $userUid,
                'avg_by_question' => $this->ratings->averageByQuestion($userUid, $locale),
            ],
        ]);
    }
}
