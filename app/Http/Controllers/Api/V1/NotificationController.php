<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Services\V1\NotificationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $unreadCount = $this->notifications->unreadCount($request->user());

        if ($unreadCount > 0) {
            $this->notifications->markAllRead($request->user());
        }

        return NotificationResource::collection($this->notifications->list($request->user()))
            ->additional([
                'success' => true,
                'meta' => [
                    'unread_count' => 0,
                ],
            ])
            ->response();
    }
}
