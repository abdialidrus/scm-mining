<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_purchase_request')) {
            return;
        }

        Schema::create('purchase_order_purchase_request', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['purchase_order_id', 'purchase_request_id']);
            $table->index(['purchase_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_purchase_request');
    }
};
