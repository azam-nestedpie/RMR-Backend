<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Connection\ActionRequest;
use App\Http\Requests\Api\V1\Connection\SendBulkRequest;
use App\Http\Requests\Api\V1\Connection\SendOnBehalfRequest;
use App\Http\Requests\Api\V1\Connection\SendRequest;
use App\Http\Resources\Api\V1\ConnectableUserResource;
use App\Http\Resources\Api\V1\ConnectionRequestResource;
use App\Models\Connection;
use App\Models\User;
use App\Services\V1\ConnectionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConnectionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ConnectionService $connections,
    ) {}

    /**
     * POST /api/v1/connections/request
     */
    public function sendRequest(SendRequest $request): JsonResponse
    {
        $targetUids = $request->validated('target_uids');
        $targets = User::query()->whereIn('firebase_uid', $targetUids)->get()->keyBy('firebase_uid');

        foreach ($targetUids as $targetUid) {
            $target = $targets->get($targetUid);

            if (! $target) {
                throw new ApiException('User not found: '.$targetUid, 404);
            }

            Gate::authorize('send', [Connection::class, $target]);
        }

        $payload = $this->connections->sendRequest($request->user(), $targetUids[0]);

        return response()->json(['success' => true, 'message' => 'Connection request sent.', 'data' => $payload], 201);
    }

    /**
     * POST /api/v1/connections/request/bulk
     */
    public function sendBulk(SendBulkRequest $request): JsonResponse
    {
        $targetUids = $request->validated('target_uids');
        $targets = User::query()->whereIn('firebase_uid', $targetUids)->get()->keyBy('firebase_uid');

        foreach ($targetUids as $targetUid) {
            $target = $targets->get($targetUid);

            if (! $target) {
                throw new ApiException('User not found: '.$targetUid, 404);
            }

            Gate::authorize('send', [Connection::class, $target]);
        }

        $payload = $this->connections->sendBulkRequest($request->user(), $targetUids);

        return response()->json(['success' => true, 'message' => 'Connection requests sent.', 'data' => $payload], 201);
    }

    public function sendRequestOnBehalf(SendOnBehalfRequest $request): JsonResponse
    {
        $behalfUser = User::query()->where('firebase_uid', $request->validated('behalf_uid'))->first();

        if (! $behalfUser) {
            throw new ApiException('User not found: '.$request->validated('behalf_uid'), 404);
        }

        $target = User::query()->where('firebase_uid', $request->validated('target_uid'))->first();

        if (! $target) {
            throw new ApiException('User not found: '.$request->validated('target_uid'), 404);
        }

        Gate::authorize('sendOnBehalf', [Connection::class, $behalfUser, $target]);

        $payload = $this->connections->sendRequestOnBehalf(
            $request->user(),
            $request->validated('behalf_uid'),
            $request->validated('target_uid')
        );

        return response()->json(['success' => true, 'message' => 'Connection request sent.', 'data' => $payload], 201);
    }

    /**
     * POST /api/v1/connections/request/{requestUuid}/accept
     */
    public function acceptRequest(ActionRequest $request): JsonResponse
    {
        $requestUuid = $request->input('request_uuid');
        $connectionRequest = $this->connections->findRequestForAuthorization($requestUuid);
        Gate::authorize('accept', [Connection::class, $connectionRequest]);

        $this->connections->acceptRequest($request->user(), $requestUuid);

        return response()->json(['success' => true, 'message' => 'Connection request accepted.']);
    }

    public function rejectRequest(ActionRequest $request): JsonResponse
    {
        $requestUuid = $request->input('request_uuid');
        $connectionRequest = $this->connections->findRequestForAuthorization($requestUuid);
        Gate::authorize('reject', [Connection::class, $connectionRequest]);

        $this->connections->rejectRequest($request->user(), $requestUuid);

        return response()->json(['success' => true, 'message' => 'Connection request rejected.']);
    }

    public function cancelRequest(ActionRequest $request): JsonResponse
    {
        $this->connections->cancelRequest($request->user(), $request->input('request_uuid'));

        return response()->json(['success' => true, 'message' => 'Connection request cancelled.']);
    }

    /** GET /api/v1/connections */
    public function index(Request $request): JsonResponse
    {
        $users = $this->connections->connectedUserList($request->user());

        return ConnectableUserResource::collection($users)
            ->additional(['success' => true])
            ->response();
    }

    /** GET /api/v1/connections/connectable */
    public function connectable(Request $request): JsonResponse
    {
        $users = $this->connections->connectableUsers($request->user());

        return ConnectableUserResource::collection($users)
            ->additional(['success' => true])
            ->response();
    }

    /** GET /api/v1/connections/requests/pending */
    public function pendingRequests(Request $request): JsonResponse
    {
        return ConnectionRequestResource::collection($this->connections->pendingRequests($request->user()->firebase_uid))
            ->additional(['success' => true])
            ->response();
    }

    public function requests(Request $request): JsonResponse
    {
        $requests = $this->connections->requests($request->user());
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

    /** DELETE /api/v1/connections/{uuid} */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $connection = $this->connections->findConnectionForAuthorization($uuid);
        Gate::authorize('disconnect', $connection);

        $this->connections->removeConnection($request->user(), $uuid);

        return response()->json(['success' => true, 'message' => 'Connection removed.']);
    }
}
