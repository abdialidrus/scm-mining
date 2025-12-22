<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();

                $table->string('gr_number')->unique();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders');
                $table->foreignId('warehouse_id')->constrained('warehouses');

                $table->string('status', 30);

                $table->timestamp('received_at')->nullable();
                $table->text('remarks')->nullable();

                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users');

                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
                $table->string('cancel_reason', 500)->nullable();

                $table->json('purchase_order_snapshot')->nullable();
                $table->json('warehouse_snapshot')->nullable();

                $table->timestamps();

                $table->index(['purchase_order_id', 'status']);
            });
        }

        if (!Schema::hasTable('goods_receipt_lines')) {
            Schema::create('goods_receipt_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->unsignedInteger('line_no');

                $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines');
                $table->foreignId('item_id')->constrained('items');
                $table->foreignId('uom_id')->constrained('uoms');

                $table->decimal('ordered_quantity', 15, 3);
                $table->decimal('received_quantity', 15, 3);

                $table->json('item_snapshot')->nullable();
                $table->json('uom_snapshot')->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->unique(['goods_receipt_id', 'line_no']);
                $table->index(['purchase_order_line_id']);
            });
        }

        if (!Schema::hasTable('goods_receipt_status_histories')) {
            Schema::create('goods_receipt_status_histories', function (Blueprint $table) {
                $table->id();

                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('action', 50);
                $table->foreignId('actor_user_id')->nullable()->constrained('users');
                $table->json('meta')->nullable();
                $table->timestamp('created_at');

                $table->index(['goods_receipt_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_status_histories');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
