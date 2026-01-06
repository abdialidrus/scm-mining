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
        Schema::create('invoice_matching_configs', function (Blueprint $table) {
            $table->id();

            // Config Type
            $table->string('config_type', 50); // GLOBAL, SUPPLIER, ITEM_CATEGORY
            $table->unsignedBigInteger('reference_id')->nullable(); // supplier_id or item_category_id

            // Tolerance Settings (dalam %)
            $table->decimal('quantity_tolerance_percent', 5, 2)->default(0);
            $table->decimal('price_tolerance_percent', 5, 2)->default(0);
            $table->decimal('amount_tolerance_percent', 5, 2)->default(0);

            // Rules
            $table->boolean('allow_under_invoicing')->default(true);
            $table->boolean('allow_over_invoicing')->default(false);
            $table->boolean('require_approval_if_variance')->default(true);

            // Auto-approval thresholds
            $table->decimal('auto_approve_if_amount_below', 20, 2)->nullable();

            // Metadata
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index(['config_type', 'reference_id']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_matching_configs');
    }
};
