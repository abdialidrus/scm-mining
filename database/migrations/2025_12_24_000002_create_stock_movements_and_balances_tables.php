<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();

                $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
                $table->foreignId('destination_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

                $table->decimal('qty', 18, 4);

                $table->string('reference_type', 100); // e.g. GOODS_RECEIPT
                $table->unsignedBigInteger('reference_id');

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('movement_at')->useCurrent();

                $table->jsonb('meta')->nullable();

                $table->timestamps();

                $table->index(['reference_type', 'reference_id']);
                $table->index(['item_id', 'source_location_id']);
                $table->index(['item_id', 'destination_location_id']);
            });
        }

        if (!Schema::hasTable('stock_balances')) {
            Schema::create('stock_balances', function (Blueprint $table) {
                $table->id();

                $table->foreignId('location_id')->constrained('warehouse_locations')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                $table->decimal('qty_on_hand', 18, 4)->default(0);

                $table->timestamp('as_of_at')->nullable();
                $table->timestamps();

                $table->unique(['location_id', 'item_id', 'uom_id']);
                $table->index(['item_id', 'location_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
    }
};
