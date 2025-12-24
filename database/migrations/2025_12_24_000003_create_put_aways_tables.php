<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('put_aways')) {
            Schema::create('put_aways', function (Blueprint $table) {
                $table->id();

                $table->string('put_away_number')->unique(); // PA-YYYYMM-XXXX

                $table->foreignId('goods_receipt_id')->constrained('goods_receipts');
                $table->foreignId('warehouse_id')->constrained('warehouses');

                $table->string('status', 30);

                $table->timestamp('put_away_at')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users');

                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users');

                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
                $table->string('cancel_reason', 500)->nullable();

                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('put_away_lines')) {
            Schema::create('put_away_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('put_away_id')->constrained('put_aways')->cascadeOnDelete();
                $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines');

                $table->foreignId('item_id')->constrained('items');
                $table->foreignId('uom_id')->nullable()->constrained('uoms');

                $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
                $table->foreignId('destination_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

                $table->decimal('qty', 18, 4);

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index(['put_away_id']);
                $table->index(['goods_receipt_line_id']);
                $table->index(['item_id']);
                $table->index(['source_location_id']);
                $table->index(['destination_location_id']);
            });
        }

        if (!Schema::hasTable('put_away_status_histories')) {
            Schema::create('put_away_status_histories', function (Blueprint $table) {
                $table->id();

                $table->foreignId('put_away_id')->constrained('put_aways')->cascadeOnDelete();

                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('action', 50);

                $table->foreignId('actor_user_id')->nullable()->constrained('users');

                $table->json('meta')->nullable();
                $table->timestamp('created_at');

                $table->index(['put_away_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('put_away_status_histories');
        Schema::dropIfExists('put_away_lines');
        Schema::dropIfExists('put_aways');
    }
};
