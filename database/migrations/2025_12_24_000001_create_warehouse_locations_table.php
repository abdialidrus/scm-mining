<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_locations')) {
            return;
        }

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

            $table->string('type'); // RECEIVING | STORAGE
            $table->string('code');
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        // Ensure exactly one default RECEIVING location per warehouse
        DB::statement("CREATE UNIQUE INDEX warehouse_locations_one_default_receiving_per_warehouse ON warehouse_locations (warehouse_id) WHERE type = 'RECEIVING' AND is_default = true");
    }

    public function down(): void
    {
        if (!Schema::hasTable('warehouse_locations')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS warehouse_locations_one_default_receiving_per_warehouse');
        Schema::dropIfExists('warehouse_locations');
    }
};
