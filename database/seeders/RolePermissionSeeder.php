<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        Permission::create(['name' => 'create pr']);
        Permission::create(['name' => 'approve pr']);
        Permission::create(['name' => 'create po']);
        Permission::create(['name' => 'approve po']);
        Permission::create(['name' => 'receive goods']);
        Permission::create(['name' => 'put away']);
        Permission::create(['name' => 'pick items']);

        $requester = Role::create(['name' => 'requester']);
        $requester->givePermissionTo(['create pr']);

        $deptHead = Role::create(['name' => 'dept_head']);
        $deptHead->givePermissionTo(['approve pr']);

        $proc = Role::create(['name' => 'procurement']);
        $proc->givePermissionTo(['create po']);

        $finance = Role::create(['name' => 'finance']);
        $finance->givePermissionTo(['approve po']);

        $warehouse = Role::create(['name' => 'warehouse']);
        $warehouse->givePermissionTo(['receive goods', 'put away', 'pick items']);
    }
}
