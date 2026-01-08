<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitializePaymentFieldsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder initializes payment fields for existing Purchase Orders
     * that were created before the payment tracking fields were added.
     */
    public function run(): void
    {
        $this->command->info('Initializing payment fields for existing Purchase Orders...');

        DB::transaction(function () {
            // Get all POs with outstanding_amount = 0 but have a total_amount
            $pos = PurchaseOrder::whereIn('status', [
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CLOSED
            ])
                ->where('payment_status', 'UNPAID')
                ->where('outstanding_amount', 0)
                ->where('total_amount', '>', 0)
                ->get();

            $this->command->info("Found {$pos->count()} POs to update");

            foreach ($pos as $po) {
                $this->command->line("Updating {$po->po_number}:");
                $this->command->line("  Total Amount: " . number_format($po->total_amount, 2));

                // Initialize outstanding amount to total amount
                $po->outstanding_amount = $po->total_amount;
                $po->total_paid = 0;

                // Set payment due date if not set
                if (!$po->payment_due_date) {
                    $baseDate = $po->sent_at ?? now();
                    $termDays = $po->payment_term_days ?? 30;
                    $po->payment_due_date = $baseDate->addDays($termDays);
                }

                $po->save();

                $this->command->line("  Outstanding Amount: " . number_format($po->outstanding_amount, 2));
                $this->command->line("  Payment Due Date: " . $po->payment_due_date->format('Y-m-d'));
                $this->command->info("  ✓ Updated\n");
            }

            $this->command->info('--- Verification ---');

            // Count eligible POs for payment
            $eligibleCount = PurchaseOrder::whereIn('status', [
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CLOSED
            ])
                ->whereIn('payment_status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
                ->where('outstanding_amount', '>', 0)
                ->count();

            $this->command->info("POs that should appear in Payments page: {$eligibleCount}");

            // Show total outstanding amount
            $totalOutstanding = PurchaseOrder::whereIn('status', [
                PurchaseOrder::STATUS_SENT,
                PurchaseOrder::STATUS_CLOSED
            ])
                ->whereIn('payment_status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
                ->sum('outstanding_amount');

            $this->command->info("Total Outstanding Amount: Rp " . number_format($totalOutstanding, 2));
        });

        $this->command->newLine();
        $this->command->info('✓ Payment fields initialized successfully!');
    }
}
