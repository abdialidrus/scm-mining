<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'subtotal_amount')) {
                $table->decimal('subtotal_amount', 18, 2)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 18, 2)->nullable()->after('subtotal_amount');
            }
            if (!Schema::hasColumn('purchase_orders', 'total_amount')) {
                $table->decimal('total_amount', 18, 2)->nullable()->after('tax_amount');
            }

            if (!Schema::hasColumn('purchase_orders', 'totals_snapshot')) {
                $table->json('totals_snapshot')->nullable()->after('tax_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'totals_snapshot')) {
                $table->dropColumn('totals_snapshot');
            }
            if (Schema::hasColumn('purchase_orders', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchase_orders', 'subtotal_amount')) {
                $table->dropColumn('subtotal_amount');
            }
        });
    }
};
