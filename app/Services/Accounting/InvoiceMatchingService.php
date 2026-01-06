<?php

namespace App\Services\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\MatchingStatus;
use App\Models\Accounting\InvoiceMatchingConfig;
use App\Models\Accounting\InvoiceMatchingResult;
use App\Models\Accounting\SupplierInvoice;
use App\Models\Accounting\SupplierInvoiceLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceMatchingService
{
    /**
     * Perform three-way match: PO vs GR vs Invoice
     */
    public function performThreeWayMatch(SupplierInvoice $invoice, User $user): InvoiceMatchingResult
    {
        DB::beginTransaction();

        try {
            // 1. Get matching config (supplier-specific or global)
            $config = $this->getMatchingConfig($invoice->supplier_id);

            // 2. Match each line
            $lineResults = [];
            $overallMatch = true;
            $requiresApproval = false;

            foreach ($invoice->lines as $line) {
                $result = $this->matchLine($line, $config);
                $lineResults[] = $result;

                // Check if any line has variance
                if ($result['status'] !== 'MATCHED') {
                    $overallMatch = false;

                    // Check if variance is within tolerance
                    if (!$result['within_tolerance']) {
                        $requiresApproval = true;
                    }
                }

                // CRITICAL: Over-invoicing is NOT allowed
                if ($result['status'] === 'OVER_INVOICED') {
                    DB::rollBack();
                    throw new \Exception("Over-invoicing detected on line {$line->line_number}. Invoice qty ({$line->invoiced_qty}) exceeds GR qty ({$result['variances']['quantity']['expected']}). This is not allowed.");
                }
            }

            // 3. Calculate overall variance
            $totalVariance = $this->calculateTotalVariance($lineResults);

            // 4. Determine overall status
            $overallStatus = $this->determineOverallStatus(
                $overallMatch,
                $requiresApproval,
                $totalVariance,
                $config
            );

            // 5. Create matching result record
            $matchingResult = InvoiceMatchingResult::create([
                'supplier_invoice_id' => $invoice->id,
                'match_type' => 'THREE_WAY',
                'overall_status' => $overallStatus,
                'total_quantity_variance' => $totalVariance['quantity'],
                'total_price_variance' => $totalVariance['price'],
                'total_amount_variance' => $totalVariance['amount'],
                'variance_percentage' => $totalVariance['percentage'],
                'config_id' => $config->id,
                'quantity_tolerance_applied' => $config->quantity_tolerance_percent,
                'price_tolerance_applied' => $config->price_tolerance_percent,
                'amount_tolerance_applied' => $config->amount_tolerance_percent,
                'requires_approval' => $requiresApproval,
                'auto_approved' => !$requiresApproval && $overallMatch,
                'matching_details' => [
                    'lines' => $lineResults,
                    'summary' => $this->generateSummary($lineResults),
                ],
                'matched_by_user_id' => $user->id,
                'matched_at' => now(),
            ]);

            // 6. Update invoice status
            $this->updateInvoiceStatus($invoice, $overallStatus, $requiresApproval);

            DB::commit();

            return $matchingResult;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Match single invoice line against PO and GR
     */
    private function matchLine(SupplierInvoiceLine $invoiceLine, InvoiceMatchingConfig $config): array
    {
        $grLine = $invoiceLine->goodsReceiptLine;
        $poLine = $invoiceLine->purchaseOrderLine;

        if (!$grLine || !$poLine) {
            throw new \Exception("Invoice line {$invoiceLine->line_number} is not linked to PO/GR. Cannot perform matching.");
        }

        // === QUANTITY CHECK (Invoice vs GR) ===
        $expectedQty = (float) $grLine->received_qty; // Yang diterima
        $invoicedQty = (float) $invoiceLine->invoiced_qty;
        $qtyVariance = $invoicedQty - $expectedQty;
        $qtyVariancePct = $expectedQty > 0 ? ($qtyVariance / $expectedQty) * 100 : 0;

        // === PRICE CHECK (Invoice vs PO) ===
        $expectedPrice = (float) $poLine->unit_price; // Harga di PO
        $invoicedPrice = (float) $invoiceLine->unit_price;
        $priceVariance = $invoicedPrice - $expectedPrice;
        $priceVariancePct = $expectedPrice > 0 ? ($priceVariance / $expectedPrice) * 100 : 0;

        // === AMOUNT CHECK ===
        $expectedAmount = $expectedQty * $expectedPrice;
        $invoicedAmount = (float) $invoiceLine->line_total;
        $amountVariance = $invoicedAmount - $expectedAmount;
        $amountVariancePct = $expectedAmount > 0 ? ($amountVariance / $expectedAmount) * 100 : 0;

        // === DETERMINE STATUS ===
        $status = MatchingStatus::MATCHED->value;
        $withinTolerance = true;

        // CRITICAL RULE: Over-invoicing NOT allowed
        if ($qtyVariance > 0) {
            $status = MatchingStatus::OVER_INVOICED->value;
            $withinTolerance = false;
        }
        // Check quantity variance (under-invoicing)
        elseif (abs($qtyVariancePct) > $config->quantity_tolerance_percent) {
            $status = MatchingStatus::QTY_VARIANCE->value;
            $withinTolerance = false;
        }
        // Check price variance
        elseif (abs($priceVariancePct) > $config->price_tolerance_percent) {
            $status = $status === MatchingStatus::QTY_VARIANCE->value
                ? MatchingStatus::BOTH_VARIANCE->value
                : MatchingStatus::PRICE_VARIANCE->value;
            $withinTolerance = false;
        }
        // Check amount variance (overall)
        elseif (abs($amountVariancePct) > $config->amount_tolerance_percent) {
            $withinTolerance = false;
        }

        // Update line with variance data
        $invoiceLine->update([
            'matching_status' => $status,
            'expected_qty' => $expectedQty,
            'qty_variance' => $qtyVariance,
            'qty_variance_percent' => round($qtyVariancePct, 2),
            'expected_price' => $expectedPrice,
            'price_variance' => $priceVariance,
            'price_variance_percent' => round($priceVariancePct, 2),
            'expected_amount' => $expectedAmount,
            'amount_variance' => $amountVariance,
            'amount_variance_percent' => round($amountVariancePct, 2),
        ]);

        return [
            'line_number' => $invoiceLine->line_number,
            'item_sku' => $invoiceLine->item->sku,
            'item_name' => $invoiceLine->item->name,
            'status' => $status,
            'within_tolerance' => $withinTolerance,
            'variances' => [
                'quantity' => [
                    'expected' => $expectedQty,
                    'invoiced' => $invoicedQty,
                    'variance' => $qtyVariance,
                    'variance_pct' => round($qtyVariancePct, 2),
                ],
                'price' => [
                    'expected' => $expectedPrice,
                    'invoiced' => $invoicedPrice,
                    'variance' => $priceVariance,
                    'variance_pct' => round($priceVariancePct, 2),
                ],
                'amount' => [
                    'expected' => $expectedAmount,
                    'invoiced' => $invoicedAmount,
                    'variance' => $amountVariance,
                    'variance_pct' => round($amountVariancePct, 2),
                ],
            ],
        ];
    }

    /**
     * Get matching config (supplier-specific or global default)
     */
    private function getMatchingConfig(int $supplierId): InvoiceMatchingConfig
    {
        // Try to get supplier-specific config
        $config = InvoiceMatchingConfig::where('config_type', 'SUPPLIER')
            ->where('reference_id', $supplierId)
            ->where('is_active', true)
            ->first();

        // Fallback to global config
        if (!$config) {
            $config = InvoiceMatchingConfig::getGlobalConfig();
        }

        return $config;
    }

    /**
     * Calculate total variance across all lines
     */
    private function calculateTotalVariance(array $lineResults): array
    {
        $totalQtyVariance = 0;
        $totalPriceVariance = 0;
        $totalAmountVariance = 0;
        $totalExpectedAmount = 0;
        $totalInvoicedAmount = 0;

        foreach ($lineResults as $line) {
            $totalQtyVariance += $line['variances']['quantity']['variance'];
            $totalPriceVariance += $line['variances']['price']['variance'];
            $totalAmountVariance += $line['variances']['amount']['variance'];
            $totalExpectedAmount += $line['variances']['amount']['expected'];
            $totalInvoicedAmount += $line['variances']['amount']['invoiced'];
        }

        $variancePercentage = $totalExpectedAmount > 0
            ? ($totalAmountVariance / $totalExpectedAmount) * 100
            : 0;

        return [
            'quantity' => $totalQtyVariance,
            'price' => $totalPriceVariance,
            'amount' => $totalAmountVariance,
            'percentage' => round($variancePercentage, 2),
        ];
    }

    /**
     * Determine overall matching status
     */
    private function determineOverallStatus(
        bool $overallMatch,
        bool $requiresApproval,
        array $totalVariance,
        InvoiceMatchingConfig $config
    ): string {
        if ($overallMatch) {
            return 'MATCHED';
        }

        if ($requiresApproval) {
            return 'VARIANCE'; // Need approval
        }

        // Within tolerance, can auto-approve
        return 'MATCHED';
    }

    /**
     * Generate summary of matching results
     */
    private function generateSummary(array $lineResults): array
    {
        $totalLines = count($lineResults);
        $matchedLines = 0;
        $varianceLines = 0;
        $overInvoicedLines = 0;

        foreach ($lineResults as $line) {
            if ($line['status'] === MatchingStatus::MATCHED->value) {
                $matchedLines++;
            } elseif ($line['status'] === MatchingStatus::OVER_INVOICED->value) {
                $overInvoicedLines++;
            } else {
                $varianceLines++;
            }
        }

        return [
            'total_lines' => $totalLines,
            'matched_lines' => $matchedLines,
            'variance_lines' => $varianceLines,
            'over_invoiced_lines' => $overInvoicedLines,
            'match_rate' => $totalLines > 0 ? round(($matchedLines / $totalLines) * 100, 2) : 0,
        ];
    }

    /**
     * Update invoice status based on matching result
     */
    private function updateInvoiceStatus(
        SupplierInvoice $invoice,
        string $matchingStatus,
        bool $requiresApproval
    ): void {
        $updates = [
            'matching_status' => $matchingStatus,
            'matched_at' => now(),
            'matched_by_user_id' => null, // Will be set by controller
        ];

        if ($matchingStatus === 'MATCHED' && !$requiresApproval) {
            // Auto-approve jika perfect match
            $updates['status'] = InvoiceStatus::APPROVED->value;
            $updates['requires_approval'] = false;
            $updates['approval_status'] = null;
        } elseif ($requiresApproval) {
            // Need approval
            $updates['status'] = InvoiceStatus::VARIANCE->value;
            $updates['requires_approval'] = true;
            $updates['approval_status'] = 'PENDING';
        } else {
            // Matched with variance within tolerance
            $updates['status'] = InvoiceStatus::MATCHED->value;
            $updates['requires_approval'] = false;
        }

        $invoice->update($updates);
    }
}
