<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_workflow_id')
                ->constrained('approval_workflows')
                ->onDelete('cascade');

            $table->integer('step_order')->default(1);
            $table->string('step_code', 50);
            $table->string('step_name');
            $table->text('step_description')->nullable();

            // Who can approve this step?
            // Types: 'ROLE', 'USER', 'DEPARTMENT_HEAD', 'DYNAMIC'
            $table->string('approver_type', 50);
            $table->text('approver_value')->nullable(); // role name, user_id, or expression

            // Conditional logic (optional)
            $table->string('condition_field', 100)->nullable(); // e.g., 'total_amount', 'department_id'
            $table->string('condition_operator', 20)->nullable(); // '>', '<', '>=', '<=', '=', 'IN', 'NOT_IN'
            $table->text('condition_value')->nullable(); // JSON or scalar value

            $table->boolean('is_required')->default(true);
            $table->boolean('allow_skip')->default(false);
            $table->boolean('allow_parallel')->default(false); // Multiple approvers at same step

            // Additional configuration
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['approval_workflow_id', 'step_order']);
            $table->unique(['approval_workflow_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};
