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
        if (Schema::hasTable('item_inventory_settings')) {
            return;
        }

        Schema::create('item_inventory_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // Stock level thresholds
            $table->decimal('reorder_point', 18, 4)->default(0)->comment('Stock level to trigger reorder');
            $table->decimal('reorder_quantity', 18, 4)->default(0)->comment('Quantity to order when reorder point is reached');
            $table->decimal('min_stock', 18, 4)->default(0)->comment('Minimum stock level');
            $table->decimal('max_stock', 18, 4)->nullable()->comment('Maximum stock level');

            // Lead time and safety stock
            $table->integer('lead_time_days')->default(7)->comment('Supplier lead time in days');
            $table->decimal('safety_stock', 18, 4)->default(0)->comment('Safety stock buffer');

            // Settings
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique constraint: one setting per item per warehouse
            // If warehouse_id is NULL, it's the default setting for all warehouses
            $table->unique(['item_id', 'warehouse_id'], 'item_warehouse_unique');
            $table->index(['item_id', 'is_active']);
            $table->index(['warehouse_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_inventory_settings');
    }
};
