<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('picking_orders')) {
            Schema::create('picking_orders', function (Blueprint $table) {
                $table->id();

                $table->string('picking_order_number')->unique(); // PK-YYYYMM-XXXX

                $table->foreignId('department_id')->nullable()->constrained('departments');
                $table->foreignId('warehouse_id')->constrained('warehouses');

                $table->string('status', 30);

                $table->string('purpose', 100)->nullable();
                $table->timestamp('picked_at')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users');

                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users');

                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
                $table->string('cancel_reason', 500)->nullable();

                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['warehouse_id', 'status']);
                $table->index(['department_id']);
                $table->index(['created_by_user_id']);
            });
        }

        if (!Schema::hasTable('picking_order_lines')) {
            Schema::create('picking_order_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('picking_order_id')->constrained('picking_orders')->cascadeOnDelete();

                $table->foreignId('item_id')->constrained('items');
                $table->foreignId('uom_id')->nullable()->constrained('uoms');

                $table->foreignId('source_location_id')->constrained('warehouse_locations')->nullOnDelete();

                $table->decimal('qty', 18, 4);

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index(['picking_order_id']);
                $table->index(['item_id']);
                $table->index(['source_location_id']);
            });
        }

        if (!Schema::hasTable('picking_order_status_histories')) {
            Schema::create('picking_order_status_histories', function (Blueprint $table) {
                $table->id();

                $table->foreignId('picking_order_id')->constrained('picking_orders')->cascadeOnDelete();

                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('action', 50);

                $table->foreignId('actor_user_id')->nullable()->constrained('users');

                $table->json('meta')->nullable();
                $table->timestamp('created_at');

                $table->index(['picking_order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_order_status_histories');
        Schema::dropIfExists('picking_order_lines');
        Schema::dropIfExists('picking_orders');
    }
};
