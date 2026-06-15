<?php

namespace App\Http\Controllers\Api\V1\Migration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Migration\ConnectionsRequest;
use App\Http\Requests\Api\V1\Migration\ExternalUsersRequest;
use App\Http\Requests\Api\V1\Migration\NotificationsRequest;
use App\Http\Requests\Api\V1\Migration\RatingsRequest;
use App\Http\Requests\Api\V1\Migration\RequestsRequest;
use App\Http\Requests\Api\V1\Migration\RunAllRequest;
use App\Http\Requests\Api\V1\Migration\UsersRequest;
use App\Services\Migration\ConnectionsMigrationService;
use App\Services\Migration\ExternalUsersMigrationService;
use App\Services\Migration\NotificationsMigrationService;
use App\Services\Migration\RatingsMigrationService;
use App\Services\Migration\RequestsMigrationService;
use App\Services\Migration\UsersMigrationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class MigrationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UsersMigrationService $users,
        private readonly ExternalUsersMigrationService $external,
        private readonly RequestsMigrationService $requests,
        private readonly ConnectionsMigrationService $connections,
        private readonly RatingsMigrationService $ratings,
        private readonly NotificationsMigrationService $notifications,
    ) {}

    public function runAll(RunAllRequest $request): JsonResponse
    {
        $results = [];

        foreach (config('migration.collections', []) as $collection) {
            $results[$collection] = $this->runCollection($collection, $request->collectionDocuments($collection));
        }

        return $this->success($results, 'All migrations completed.');
    }

    public function runUsers(UsersRequest $request): JsonResponse
    {
        return $this->success($this->users->migrate($request->collectionDocuments('users')), 'Users migration complete.');
    }

    public function runExternalUsers(ExternalUsersRequest $request): JsonResponse
    {
        return $this->success($this->external->migrate($request->collectionDocuments('external_users')), 'External users migration complete.');
    }

    public function runRequests(RequestsRequest $request): JsonResponse
    {
        return $this->success($this->requests->migrate($request->collectionDocuments('requests')), 'Requests migration complete.');
    }

    public function runConnections(ConnectionsRequest $request): JsonResponse
    {
        return $this->success($this->connections->migrate($request->collectionDocuments('connections')), 'Connections migration complete.');
    }

    public function runRatings(RatingsRequest $request): JsonResponse
    {
        return $this->success($this->ratings->migrate($request->collectionDocuments('ratings')), 'Ratings migration complete.');
    }

    public function runNotifications(NotificationsRequest $request): JsonResponse
    {
        return $this->success($this->notifications->migrate($request->collectionDocuments('notifications')), 'Notifications migration complete.');
    }

    private function runCollection(string $collection, array $documents): array
    {
        return match ($collection) {
            'users' => $this->users->migrate($documents),
            'external_users' => $this->external->migrate($documents),
            'requests' => $this->requests->migrate($documents),
            'connections' => $this->connections->migrate($documents),
            'ratings' => $this->ratings->migrate($documents),
            'notifications' => $this->notifications->migrate($documents),
            default => ['success' => 0, 'failed' => 0, 'skipped' => 0],
        };
    }
}
