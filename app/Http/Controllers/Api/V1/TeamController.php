<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Team\ActionRequest;
use App\Http\Requests\Api\V1\Team\SendRequest;
use App\Http\Requests\Api\V1\User\SearchRequest;
use App\Http\Resources\Api\V1\CompletedRatingResource;
use App\Http\Resources\Api\V1\ConnectableUserResource;
use App\Http\Resources\Api\V1\RatingRequestResource;
use App\Http\Resources\Api\V1\TeamMemberNetworkResource;
use App\Http\Resources\Api\V1\TeamMemberResource;
use App\Models\Role;
use App\Models\User;
use App\Services\V1\RatingService;
use App\Services\V1\TeamService;
use App\Services\V1\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TeamService $team,
        private readonly UserService $users,
        private readonly RatingService $ratings,
    ) {}

    public function store(SendRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Team invitations sent.',
            'data' => $this->team->send($request->user(), $request->validated('target_uids')),
        ], 201);
    }

    public function searchForInvite(SearchRequest $request): JsonResponse
    {
        return ConnectableUserResource::collection(
            $this->team->searchForInvite($request->user(), $request->validated())
        )
            ->additional(['success' => true])
            ->response();
    }

    public function pending(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->team->pending($request->user()),
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleId = $user?->roles?->first()?->id;

        return response()->json([
            'success' => true,
            'data' => match ($roleId) {
                Role::MANAGER_OF_REPRESENTATIVES, Role::MANAGER_OF_RATERS => $this->team->requests($user)['sent']->map(function ($req) use ($request) {
                    $target = $req->relationLoaded('target') ? $req->target : null;
                    $target?->loadMissing('salesRepProfile');

                    return array_merge(
                        $target ? (new ConnectableUserResource($target))->resolve($request) : [],
                        ['request_uuid' => $req->firebase_uuid, 'status' => $req->status?->name]
                    );
                })->values()->toArray(),
                default => $this->team->requests($user)['received']->map(function ($req) use ($request) {
                    $manager = $req->relationLoaded('manager') ? $req->manager : null;
                    $manager?->loadMissing('salesRepProfile');

                    return array_merge(
                        $manager ? (new ConnectableUserResource($manager))->resolve($request) : [],
                        ['request_uuid' => $req->firebase_uuid, 'status' => $req->status?->name]
                    );
                })->values()->toArray(),
            },
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        return TeamMemberResource::collection($this->team->members($request->user()))
            ->additional(['success' => true])
            ->response();
    }

    public function accept(ActionRequest $request): JsonResponse
    {
        $this->team->accept($request->user(), $request->input('request_uuid'));

        return response()->json(['success' => true, 'message' => 'Team invitation accepted.']);
    }

    public function reject(ActionRequest $request): JsonResponse
    {
        $this->team->reject($request->user(), $request->input('request_uuid'));

        return response()->json(['success' => true, 'message' => 'Team invitation rejected.']);
    }

    public function cancel(ActionRequest $request): JsonResponse
    {
        $this->team->cancel($request->user(), $request->input('request_uuid'));

        return response()->json(['success' => true, 'message' => 'Team invitation cancelled.']);
    }

    public function destroy(Request $request, string $memberUid): JsonResponse
    {
        $this->team->destroy($request->user(), $memberUid);

        return response()->json(['success' => true, 'message' => 'Team member removed.']);
    }

    public function leave(Request $request, string $managerUid): JsonResponse
    {
        $this->team->leave($request->user(), $managerUid);

        return response()->json(['success' => true, 'message' => 'You left the team.']);
    }

    /**
     * Get a team member's network (connections, sent/received requests).
     */
    public function network(Request $request, string $userUid): JsonResponse
    {
        $manager = $request->user();

        $member = User::where('firebase_uid', $userUid)->first();

        if (! $member) {
            return $this->notFound('User not found.');
        }

        if (! $manager->manages($member->firebase_uid)) {
            return $this->forbidden('This user is not a member of your team.');
        }

        $network = $this->users->teamMemberNetwork($member);

        return response()->json([
            'success' => true,
            'data' => [
                'connections' => TeamMemberNetworkResource::collection($network['connections']),
                'requests' => [
                    'sent' => TeamMemberNetworkResource::collection($network['sentRequests']),
                    'received' => TeamMemberNetworkResource::collection($network['receivedRequests']),
                ],
            ],
        ]);
    }

    /**
     * Get a team member's rating activity (pending requests + completed ratings).
     */
    public function ratings(Request $request, string $userUid): JsonResponse
    {
        $manager = $request->user();

        $member = User::where('firebase_uid', $userUid)->first();

        if (! $member) {
            return $this->notFound('User not found.');
        }

        if (! $manager->manages($member->firebase_uid)) {
            return $this->forbidden('This user is not a member of your team.');
        }

        $ratingActivity = $this->ratings->teamMemberRatings($member);

        return response()->json([
            'success' => true,
            'data' => [
                'pending_requests' => RatingRequestResource::collection($ratingActivity['pendingRequests']),
                'completed_ratings' => CompletedRatingResource::collection($ratingActivity['completedRatings']),
            ],
        ]);
    }
}
