<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('payment_status', 30)->default('UNPAID')->after('status');
            $table->integer('payment_term_days')->default(30)->after('payment_status');
            $table->date('payment_due_date')->nullable()->after('payment_term_days');
            $table->decimal('total_paid', 20, 2)->default(0)->after('payment_due_date');
            $table->decimal('outstanding_amount', 20, 2)->default(0)->after('total_paid');

            $table->index('payment_status');
            $table->index('payment_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_due_date']);

            $table->dropColumn([
                'payment_status',
                'payment_term_days',
                'payment_due_date',
                'total_paid',
                'outstanding_amount',
            ]);
        });
    }
};
