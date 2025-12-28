<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_workflow_id')
                ->constrained('approval_workflows')
                ->onDelete('cascade');

            $table->foreignId('approval_workflow_step_id')
                ->constrained('approval_workflow_steps')
                ->onDelete('cascade');

            // Polymorphic relation to approvable model (PO, PR, etc.)
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');

            // Approval state: PENDING, APPROVED, REJECTED, SKIPPED, CANCELLED
            $table->string('status', 50)->default('PENDING');

            // Who should approve?
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->string('assigned_to_role', 100)->nullable();

            // Who actually approved?
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Rejection
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Additional info
            $table->text('comments')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['status']);
            $table->index(['assigned_to_user_id']);
            $table->index(['assigned_to_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
