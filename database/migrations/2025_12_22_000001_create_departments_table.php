<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();

                $table->string('code')->unique();
                $table->string('name');

                $table->foreignId('parent_id')->nullable()->constrained('departments');
                $table->foreignId('head_user_id')->nullable()->constrained('users');

                $table->timestamps();

                $table->index(['parent_id']);
                $table->index(['head_user_id']);
            });

            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'head_user_id')) {
                $table->foreignId('head_user_id')->nullable()->constrained('users');
                $table->index(['head_user_id']);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (Schema::hasColumn('departments', 'head_user_id')) {
                    $table->dropConstrainedForeignId('head_user_id');
                }
            });
        }
    }
};
