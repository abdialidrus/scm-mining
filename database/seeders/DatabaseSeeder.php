<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(UomSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(ApprovalWorkflowSeeder::class);

        $user = User::query()->updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'department_id' => 1,
            ],
        );

        $superAdminRole = Role::query()->where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $user->syncRoles([$superAdminRole]);
        }
    }
}
