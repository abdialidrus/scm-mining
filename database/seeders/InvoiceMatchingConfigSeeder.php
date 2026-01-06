<?php

namespace Database\Seeders;

use App\Models\Accounting\InvoiceMatchingConfig;
use Illuminate\Database\Seeder;

class InvoiceMatchingConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default global config if not exists
        InvoiceMatchingConfig::firstOrCreate(
            [
                'config_type' => 'GLOBAL',
                'is_active' => true,
            ],
            [
                'quantity_tolerance_percent' => 0.00,
                'price_tolerance_percent' => 0.00,
                'amount_tolerance_percent' => 0.00,
                'allow_under_invoicing' => true,
                'allow_over_invoicing' => false,
                'require_approval_if_variance' => true,
                'notes' => 'Default global matching configuration - Zero tolerance policy. All invoices must exactly match PO and GR quantities and prices.',
                'created_by_user_id' => 1, // System/Admin user
            ]
        );

        $this->command->info('Invoice matching config seeded successfully!');
    }
}
