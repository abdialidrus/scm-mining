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

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['head:id,name,email']);

        return response()->json([
            'data' => $department,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:departments,code',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id',
        ]);

        $validated['created_by_user_id'] = $request->user()->id;
        $validated['updated_by_user_id'] = $request->user()->id;

        $department = Department::create($validated);
        $department->load(['head:id,name,email']);

        return response()->json([
            'data' => $department,
            'message' => 'Department created successfully',
        ], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:departments,code,' . $department->id,
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id',
        ]);

        // Prevent self as parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $department->id) {
            return response()->json([
                'message' => 'A department cannot be its own parent',
                'errors' => [
                    'parent_id' => ['A department cannot be its own parent']
                ]
            ], 422);
        }

        // Prevent circular reference - check if new parent is a descendant
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            if ($this->isDescendant($department->id, $validated['parent_id'])) {
                return response()->json([
                    'message' => 'Circular reference detected: Cannot set a descendant as parent',
                    'errors' => [
                        'parent_id' => ['Circular reference detected: Cannot set a descendant as parent']
                    ]
                ], 422);
            }
        }

        $validated['updated_by_user_id'] = $request->user()->id;

        $department->update($validated);
        $department->load(['head:id,name,email']);

        return response()->json([
            'data' => $department,
            'message' => 'Department updated successfully',
        ]);
    }

    /**
     * Check if a department is a descendant of another
     */
    private function isDescendant(int $departmentId, int $potentialAncestorId): bool
    {
        $current = Department::find($potentialAncestorId);

        while ($current && $current->parent_id) {
            if ($current->parent_id == $departmentId) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    public function destroy(Department $department): JsonResponse
    {
        try {
            $department->delete();

            return response()->json([
                'message' => 'Department deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete department: ' . $e->getMessage(),
            ], 422);
        }
    }
}
