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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->onDelete('restrict');

            // Payment Info
            $table->string('payment_number', 100)->unique(); // PAY-YYYYMM-XXXX
            $table->date('payment_date');
            $table->string('payment_method', 50)->default('BANK_TRANSFER');
            // BANK_TRANSFER, CASH, CHECK, GIRO

            $table->decimal('payment_amount', 20, 2);

            // Bank Details
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_name', 255)->nullable();
            $table->string('transaction_reference', 255)->nullable();

            // Attachments
            $table->string('payment_proof_path', 500)->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Indexes
            $table->index('supplier_invoice_id');
            $table->index('payment_date');
            $table->index('payment_number');
        });

        // Add check constraint using raw SQL
        DB::statement('ALTER TABLE invoice_payments ADD CONSTRAINT chk_payment_amount CHECK (payment_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
