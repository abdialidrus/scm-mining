<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        // For now: allow any authenticated user to read roles.
        // User management UI is still protected; this endpoint is read-only.
        return response()->json([
            'data' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
