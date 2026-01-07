<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->onDelete('cascade');
            $table->integer('line_number'); // Sequential per invoice

            // Item Info
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->foreignId('uom_id')->constrained('uoms')->onDelete('restrict');

            // Quantities & Prices (from invoice)
            $table->decimal('invoiced_qty', 18, 4);
            $table->decimal('unit_price', 20, 2);

            // Discounts & Tax (per line - optional)
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);

            // Line Total
            $table->decimal('line_total', 20, 2);

            // Matching References
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->onDelete('set null');
            $table->foreignId('goods_receipt_line_id')->nullable()->constrained('goods_receipt_lines')->onDelete('set null');

            // Matching Status per Line
            $table->string('matching_status', 30)->default('PENDING');
            // PENDING, MATCHED, QTY_VARIANCE, PRICE_VARIANCE, BOTH_VARIANCE, OVER_INVOICED

            // Variance Details (calculated during matching)
            $table->decimal('expected_qty', 18, 4)->nullable();
            $table->decimal('qty_variance', 18, 4)->nullable();
            $table->decimal('qty_variance_percent', 5, 2)->nullable();

            $table->decimal('expected_price', 20, 2)->nullable();
            $table->decimal('price_variance', 20, 2)->nullable();
            $table->decimal('price_variance_percent', 5, 2)->nullable();

            $table->decimal('expected_amount', 20, 2)->nullable();
            $table->decimal('amount_variance', 20, 2)->nullable();
            $table->decimal('amount_variance_percent', 5, 2)->nullable();

            // Notes
            $table->text('matching_notes')->nullable();
            $table->text('remarks')->nullable();

            // Audit
            $table->timestamps();

            // Indexes
            $table->index('supplier_invoice_id');
            $table->index('item_id');
            $table->index('purchase_order_line_id');
            $table->index('goods_receipt_line_id');
            $table->unique(['supplier_invoice_id', 'line_number']);
        });

        // Add check constraints using raw SQL (only for non-SQLite)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT chk_line_amounts CHECK (invoiced_qty > 0 AND unit_price >= 0 AND line_total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
    }
};
