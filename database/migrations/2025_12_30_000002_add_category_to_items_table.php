<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'item_category_id')) {
                $table->foreignId('item_category_id')->nullable()->after('base_uom_id')->constrained('item_categories')->nullOnDelete();
                $table->index(['item_category_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'item_category_id')) {
                $table->dropConstrainedForeignId('item_category_id');
            }
        });
    }
};
