<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This project already has an `items` table (created by an earlier migration not present in the repo).
        // For now we only add UOM foreign key if `uoms` exists and the column doesn't yet exist.
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();

                $table->string('sku')->unique();
                $table->string('name');

                $table->boolean('is_serialized')->default(false);
                $table->unsignedTinyInteger('criticality_level')->default(3);

                $table->foreignId('base_uom_id')->constrained('uoms');

                $table->timestamps();

                $table->index(['base_uom_id']);
            });

            return;
        }

        if (Schema::hasTable('uoms') && !Schema::hasColumn('items', 'base_uom_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('base_uom_id')->nullable()->constrained('uoms');
                $table->index(['base_uom_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'base_uom_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('base_uom_id');
            });
        }
    }
};
