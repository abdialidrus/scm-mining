<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing table is `purchase_request_items`.
        if (!Schema::hasTable('purchase_request_items')) {
            Schema::create('purchase_request_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
                $table->unsignedInteger('line_no');

                $table->foreignId('item_id')->constrained('items');
                $table->decimal('quantity', 18, 3);
                $table->foreignId('uom_id')->constrained('uoms');

                $table->text('remark')->nullable();

                $table->timestamps();

                $table->unique(['purchase_request_id', 'line_no']);
            });

            return;
        }

        // migrate existing schema to our target
        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_request_items', 'line_no')) {
                $table->unsignedInteger('line_no')->default(1);
            }

            if (Schema::hasTable('uoms') && !Schema::hasColumn('purchase_request_items', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->constrained('uoms');
                $table->index(['uom_id']);
            }

            if (!Schema::hasColumn('purchase_request_items', 'remarks') && Schema::hasColumn('purchase_request_items', 'remark')) {
                $table->renameColumn('remark', 'remarks');
            }
        });
    }

    public function down(): void
    {
        // No destructive down.
    }
};
