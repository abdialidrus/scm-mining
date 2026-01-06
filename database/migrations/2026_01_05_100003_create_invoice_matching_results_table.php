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
        Schema::create('invoice_matching_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->onDelete('cascade');

            // Matching Info
            $table->string('match_type', 30)->default('THREE_WAY');
            $table->string('overall_status', 30);
            // MATCHED, VARIANCE, REJECTED

            // Summary Variance (aggregate dari semua lines)
            $table->decimal('total_quantity_variance', 18, 4)->default(0);
            $table->decimal('total_price_variance', 20, 2)->default(0);
            $table->decimal('total_amount_variance', 20, 2)->default(0);
            $table->decimal('variance_percentage', 5, 2)->default(0);

            // Config used during matching
            $table->foreignId('config_id')->nullable()->constrained('invoice_matching_configs')->onDelete('set null');
            $table->decimal('quantity_tolerance_applied', 5, 2)->nullable();
            $table->decimal('price_tolerance_applied', 5, 2)->nullable();
            $table->decimal('amount_tolerance_applied', 5, 2)->nullable();

            // Decision
            $table->boolean('requires_approval')->default(false);
            $table->boolean('auto_approved')->default(false);
            $table->text('rejection_reason')->nullable();

            // Line-by-line details (JSONB for flexibility)
            $table->jsonb('matching_details')->nullable();

            // Audit
            $table->foreignId('matched_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('matched_at');
            $table->timestamps();

            // Indexes
            $table->index('supplier_invoice_id');
            $table->index('overall_status');
            $table->index('matched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_matching_results');
    }
};
