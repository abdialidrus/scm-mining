<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_lines')) {
            return;
        }

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_no');

            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 18, 3);
            $table->foreignId('uom_id')->constrained('uoms');

            // price fields (kept minimal for now)
            $table->decimal('unit_price', 18, 2)->default(0);

            // snapshots
            $table->json('item_snapshot')->nullable();
            $table->json('uom_snapshot')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index(['purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
