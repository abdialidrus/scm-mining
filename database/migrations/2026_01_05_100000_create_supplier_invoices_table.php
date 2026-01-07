<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();

            // Invoice Identification
            $table->string('invoice_number', 100); // Nomor invoice dari supplier
            $table->string('internal_number', 50)->unique(); // INV-YYYYMM-XXXX

            // Supplier & PO Reference
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('restrict');

            // Invoice Dates
            $table->date('invoice_date');
            $table->date('received_date'); // Tanggal invoice diterima perusahaan
            $table->date('due_date')->nullable(); // Kapan harus dibayar

            // Currency (IDR only)
            $table->string('currency', 10)->default('IDR');

            // Status Workflow
            $table->string('status', 30)->default('DRAFT');
            // DRAFT, SUBMITTED, MATCHED, VARIANCE, APPROVED, PAID, REJECTED, CANCELLED

            // Matching Status
            $table->string('matching_status', 30)->nullable();
            // PENDING, MATCHED, PARTIAL_MATCH, MISMATCHED
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Financial Details
            $table->decimal('subtotal', 20, 2);
            $table->decimal('tax_rate', 5, 2)->default(0); // % PPN (optional)
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('other_charges', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2); // Grand total

            // Payment Tracking
            $table->string('payment_status', 30)->default('UNPAID');
            // UNPAID, PARTIAL_PAID, PAID, OVERDUE
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2)->nullable();
            $table->string('payment_terms', 100)->default('CASH');

            // Approval (untuk variance)
            $table->boolean('requires_approval')->default(false);
            $table->string('approval_status', 30)->nullable();
            // NULL, PENDING, APPROVED, REJECTED
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Attachments
            $table->string('invoice_file_path', 500)->nullable();

            // Additional Info
            $table->string('supplier_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index('supplier_id');
            $table->index('purchase_order_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('invoice_number');
            $table->index('internal_number');
            $table->index('invoice_date');
        });

        // Add check constraints using raw SQL (only for non-SQLite)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE supplier_invoices ADD CONSTRAINT chk_invoice_amounts CHECK (subtotal >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND paid_amount <= total_amount)');
        }

        // Unique constraint: One invoice number per supplier (unless cancelled)
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->unique(['supplier_id', 'invoice_number'], 'unique_invoice_per_supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
