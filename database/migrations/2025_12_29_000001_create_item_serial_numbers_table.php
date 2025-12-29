<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('item_serial_numbers')) {
            Schema::create('item_serial_numbers', function (Blueprint $table) {
                $table->id();

                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->string('serial_number', 100)->unique();
                $table->string('status', 30)->default('AVAILABLE'); // AVAILABLE, PICKED, DAMAGED, DISPOSED

                $table->foreignId('current_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

                // Lifecycle tracking - Goods Receipt
                $table->timestamp('received_at')->nullable();
                $table->foreignId('goods_receipt_line_id')->nullable()->constrained('goods_receipt_lines')->nullOnDelete();

                // Lifecycle tracking - Picking
                $table->timestamp('picked_at')->nullable();
                $table->foreignId('picking_order_line_id')->nullable()->constrained('picking_order_lines')->nullOnDelete();

                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['item_id', 'status']);
                $table->index(['current_location_id', 'status']);
                $table->index(['serial_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_serial_numbers');
    }
};
