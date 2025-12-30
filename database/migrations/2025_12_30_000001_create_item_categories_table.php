<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Hierarchy support
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->nullOnDelete();

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(false); // For sensitive categories like Explosives

            // UI helpers
            $table->string('color_code', 20)->nullable(); // For visual identification
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['parent_id', 'is_active']);
            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
