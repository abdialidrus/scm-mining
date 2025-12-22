<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_request_status_histories')) {
            return;
        }

        Schema::create('purchase_request_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->string('action');

            $table->foreignId('actor_user_id')->nullable()->constrained('users');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['purchase_request_id', 'created_at']);
            $table->index(['to_status']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_status_histories');
    }
};
