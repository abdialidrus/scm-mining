<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_status_histories', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_request_status_histories', 'meta')) {
                return;
            }

            $table->jsonb('meta')->nullable()->after('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_status_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_request_status_histories', 'meta')) {
                return;
            }

            $table->dropColumn('meta');
        });
    }
};
