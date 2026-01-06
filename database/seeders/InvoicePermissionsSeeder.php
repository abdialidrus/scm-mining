<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InvoicePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'invoices.match',
            'invoices.approve',
            'invoices.export',
            'invoices.payment.record',
            'invoices.tolerance.configure',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles

        // Super Admin - Full access
        $superAdmin = Role::findByName('super_admin');
        $superAdmin->givePermissionTo($permissions);

        // Finance - Full CRUD, matching, payment, tolerance config
        $finance = Role::findByName('finance');
        $finance->givePermissionTo([
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'invoices.match',
            'invoices.payment.record',
            'invoices.tolerance.configure',
            'invoices.export',
        ]);

        // GM & Director - View only
        $gm = Role::findByName('gm');
        $gm->givePermissionTo(['invoices.view', 'invoices.export']);

        $director = Role::findByName('director');
        $director->givePermissionTo(['invoices.view', 'invoices.export']);

        // Dept Head - Can approve invoices (when combined with finance role)
        // Note: Approval requires both 'finance' AND 'dept_head' roles
        // We'll handle this in the Policy

        $this->command->info('Invoice permissions seeded successfully!');
    }
}
