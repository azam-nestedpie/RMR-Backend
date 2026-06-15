<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\V1\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly DashboardService $dashboards,
    ) {}

    /**
     * GET /api/v1/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return $this->success($this->dashboards->dashboard($request->user()));
        } catch (AuthorizationException $e) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
    }

    /**
     * POST /api/v1/dashboard/export-email
     */
    public function exportEmail(Request $request): JsonResponse
    {
        try {
            return $this->success(
                $this->dashboards->queueEmailExport($request->user()),
                'Dashboard export email queued.'
            );
        } catch (AuthorizationException $e) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
    }

    public function home(Request $request): JsonResponse
    {
        try {
            return $this->success($this->dashboards->managerHome($request->user()));
        } catch (AuthorizationException $e) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
    }

    public function raterHome(Request $request): JsonResponse
    {
        return $this->success($this->dashboards->raterHome($request->user()));
    }

    public function repHome(Request $request): JsonResponse
    {
        return $this->success($this->dashboards->repHome($request->user()));
    }
}
