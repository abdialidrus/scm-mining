<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\StoreUserRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->with(['roles:name', 'department:id,code,name'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%');
            });
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user->load(['roles:name', 'department:id,code,name']),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::query()->create([
            'name' => $request->validated()['name'],
            'email' => $request->validated()['email'],
            'password' => $request->validated()['password'],
            'department_id' => $request->validated()['department_id'] ?? null,
        ]);

        $roles = $request->validated()['roles'] ?? [];
        if (!empty($roles)) {
            $user->syncRoles($roles);
        }

        return response()->json([
            'data' => $user->load(['roles:name', 'department:id,code,name']),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ]);

        if (array_key_exists('department_id', $data)) {
            $user->department_id = $data['department_id'];
        }

        if (!empty($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return response()->json([
            'data' => $user->load(['roles:name', 'department:id,code,name']),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
