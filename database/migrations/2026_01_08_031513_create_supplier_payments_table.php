<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            // Invoice info from supplier
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->decimal('supplier_invoice_amount', 20, 2)->nullable();
            $table->string('supplier_invoice_file_path', 500)->nullable();

            // Payment info
            $table->date('payment_date');
            $table->decimal('payment_amount', 20, 2);
            $table->string('payment_method', 50); // TRANSFER, CASH, CHECK, GIRO
            $table->string('payment_reference', 100)->nullable(); // Transfer/check/giro number
            $table->string('payment_proof_file_path', 500)->nullable();

            // Bank info
            $table->string('bank_account_from', 100)->nullable();
            $table->string('bank_account_to', 100)->nullable();

            // Status & notes
            $table->string('status', 30)->default('DRAFT'); // DRAFT, CONFIRMED, CANCELLED
            $table->text('notes')->nullable();

            // Approval (optional)
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index('purchase_order_id');
            $table->index('status');
            $table->index('payment_date');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
