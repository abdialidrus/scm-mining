<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('po_number')->unique(); // PO-YYYYMM-XXXX

            $table->foreignId('supplier_id')->constrained('suppliers');

            $table->string('status')->default('DRAFT');

            $table->string('currency_code')->default('IDR');

            $table->decimal('tax_rate', 8, 6)->default(0.11); // 11% by default

            // Snapshot at creation time (audit-friendly)
            $table->json('supplier_snapshot')->nullable();
            $table->json('tax_snapshot')->nullable();

            // Approval tracking
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users');

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');

            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users');

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users');

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
            $table->text('cancel_reason')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
