<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * Get all roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::select([
            'id',
            'name',
            'description',

        ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles fetched successfully',
            'data' => $roles,
        ]);
    }
}
