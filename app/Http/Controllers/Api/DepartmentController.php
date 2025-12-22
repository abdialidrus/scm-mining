<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()
            ->with(['head:id,name,email'])
            ->orderBy('code');

        // optional: simple search on code/name
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', '%' . $search . '%')
                    ->orWhere('name', 'ilike', '%' . $search . '%');
            });
        }

        return response()->json([
            'data' => $query->get(['id', 'code', 'name', 'parent_id', 'head_user_id']),
        ]);
    }
}
