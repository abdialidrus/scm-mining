<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'create pr',
            'approve pr',
            'create po',
            'approve po',
            'receive goods',
            'put away',
            'pick items',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $requester = Role::query()->firstOrCreate(['name' => 'requester', 'guard_name' => 'web']);
        $requester->givePermissionTo(['create pr']);

        $deptHead = Role::query()->firstOrCreate(['name' => 'dept_head', 'guard_name' => 'web']);
        $deptHead->givePermissionTo(['approve pr']);

        $proc = Role::query()->firstOrCreate(['name' => 'procurement', 'guard_name' => 'web']);
        $proc->givePermissionTo(['create po']);

        $finance = Role::query()->firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
        $finance->givePermissionTo(['approve po']);

        $warehouse = Role::query()->firstOrCreate(['name' => 'warehouse', 'guard_name' => 'web']);
        $warehouse->givePermissionTo(['receive goods', 'put away', 'pick items']);

        // Super admin has all permissions.
        $superAdmin = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::query()->pluck('name')->all());
    }
}
