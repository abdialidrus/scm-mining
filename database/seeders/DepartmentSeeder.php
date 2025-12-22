<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $eng = Department::query()->updateOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Engineering', 'parent_id' => null],
        );

        $mtc = Department::query()->updateOrCreate(
            ['code' => 'MTC'],
            ['name' => 'Maintenance', 'parent_id' => null],
        );

        $proc = Department::query()->updateOrCreate(
            ['code' => 'PROC'],
            ['name' => 'Procurement', 'parent_id' => null],
        );

        $fin = Department::query()->updateOrCreate(
            ['code' => 'FIN'],
            ['name' => 'Finance', 'parent_id' => null],
        );

        // Roles (expected to be seeded by RolePermissionSeeder, but keep this safe/idempotent)
        $deptHeadRole = Role::query()->firstOrCreate(['name' => 'dept_head']);
        $requesterRole = Role::query()->firstOrCreate(['name' => 'requester']);

        // Users
        $engHead = User::query()->updateOrCreate(
            ['email' => 'eng.head@demo.test'],
            [
                'name' => 'ENG Head',
                'password' => Hash::make('password'),
                'department_id' => $eng->id,
            ],
        );
        $engHead->syncRoles([$deptHeadRole]);

        $engRequester = User::query()->updateOrCreate(
            ['email' => 'eng.requester@demo.test'],
            [
                'name' => 'ENG Requester',
                'password' => Hash::make('password'),
                'department_id' => $eng->id,
            ],
        );
        $engRequester->syncRoles([$requesterRole]);

        // Link department head_user_id (approval uses this)
        if ($eng->head_user_id !== $engHead->id) {
            $eng->head_user_id = $engHead->id;
            $eng->save();
        }

        // Optional: create dept heads for other departments (useful for future flows)
        $mtcHead = User::query()->updateOrCreate(
            ['email' => 'mtc.head@demo.test'],
            [
                'name' => 'MTC Head',
                'password' => Hash::make('password'),
                'department_id' => $mtc->id,
            ],
        );
        $mtcHead->syncRoles([$deptHeadRole]);
        if ($mtc->head_user_id !== $mtcHead->id) {
            $mtc->head_user_id = $mtcHead->id;
            $mtc->save();
        }

        $procHead = User::query()->updateOrCreate(
            ['email' => 'proc.head@demo.test'],
            [
                'name' => 'PROC Head',
                'password' => Hash::make('password'),
                'department_id' => $proc->id,
            ],
        );
        $procHead->syncRoles([$deptHeadRole]);
        if ($proc->head_user_id !== $procHead->id) {
            $proc->head_user_id = $procHead->id;
            $proc->save();
        }

        $finHead = User::query()->updateOrCreate(
            ['email' => 'fin.head@demo.test'],
            [
                'name' => 'FIN Head',
                'password' => Hash::make('password'),
                'department_id' => $fin->id,
            ],
        );
        $finHead->syncRoles([$deptHeadRole]);
        if ($fin->head_user_id !== $finHead->id) {
            $fin->head_user_id = $finHead->id;
            $fin->save();
        }
    }
}
