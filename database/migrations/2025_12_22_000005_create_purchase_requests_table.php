<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_requests')) {
            Schema::create('purchase_requests', function (Blueprint $table) {
                $table->id();

                $table->string('pr_number')->unique(); // PR-YYYYMM-XXXX

                $table->foreignId('requester_user_id')->constrained('users');
                $table->foreignId('department_id')->constrained('departments');

                $table->string('status')->default('DRAFT');

                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users');

                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users');

                $table->timestamp('converted_to_po_at')->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index(['department_id', 'status']);
                $table->index(['requester_user_id']);
            });

            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            // Existing schema uses `requested_by`.
            if (Schema::hasColumn('purchase_requests', 'requested_by') && !Schema::hasColumn('purchase_requests', 'requester_user_id')) {
                $table->renameColumn('requested_by', 'requester_user_id');
            }

            foreach (['submitted_at', 'approved_at', 'converted_to_po_at'] as $col) {
                if (!Schema::hasColumn('purchase_requests', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }

            foreach (['submitted_by_user_id', 'approved_by_user_id'] as $col) {
                if (!Schema::hasColumn('purchase_requests', $col)) {
                    $table->foreignId($col)->nullable()->constrained('users');
                }
            }

            if (!Schema::hasColumn('purchase_requests', 'remarks')) {
                $table->text('remarks')->nullable();
            }

            if (!Schema::hasColumn('purchase_requests', 'status')) {
                $table->string('status')->default('DRAFT');
                $table->index(['status']);
            }
        });
    }

    public function down(): void
    {
        // No destructive down; keep it safe.
    }
};
