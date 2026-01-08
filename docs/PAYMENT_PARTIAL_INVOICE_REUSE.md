# Payment Module - Partial Payment Invoice Reuse Feature

## Overview

When recording multiple payments for the same Purchase Order (partial payments), users can now reuse invoice information from previous payments instead of entering the same invoice details repeatedly.

## Feature Details

### When It Activates

The feature automatically appears when:

1. PO has `payment_status = 'PARTIAL'`
2. PO has at least one confirmed payment with invoice information

### User Flow

#### 1. Access Payment Form

- Navigate to: `/payments/purchase-orders/{id}/create`
- If PO has partial payment status, see blue info box

#### 2. Choose Invoice Mode

**Option A: Enter New Invoice**

- Default mode
- Fill in all invoice fields manually
- Upload new invoice PDF

**Option B: Use Existing Invoice**

- Select from dropdown of previous payments
- Invoice details auto-filled:
    - Invoice Number
    - Invoice Date
    - Invoice Amount
- Fields become read-only
- File upload optional (invoice already uploaded in previous payment)

### UI Components

#### Info Box (Blue Alert)

```
⚠️ Partial Payment Detected

This PO has previous payments. You can reuse invoice
information or enter new invoice details.

○ Enter new invoice  ● Use existing invoice

[Select Previous Payment dropdown]
```

#### Dropdown Format

```
INV-2026-001 - Rp 28,860,000 (7 Jan 2026)
INV-2026-002 - Rp 15,000,000 (8 Jan 2026)
```

### Technical Implementation

#### Backend Changes

**`PaymentController::create()` Enhancement:**

```php
public function create(PurchaseOrder $purchaseOrder): JsonResponse
{
    $purchaseOrder->load([
        'supplier',
        'goodsReceipts',
        'payments' => function ($query) {
            $query->where('status', 'CONFIRMED')
                  ->whereNotNull('supplier_invoice_number')
                  ->latest();
        }
    ]);

    return response()->json([
        'success' => true,
        'data' => $purchaseOrder,
    ]);
}
```

**Response includes:**

- PO details
- Supplier info
- Goods receipts
- **Previous confirmed payments with invoice info**

#### Frontend Changes

**New State Variables:**

```typescript
const useExistingInvoice = ref(false);
const selectedPaymentId = ref<number | undefined>(undefined);
```

**Computed Properties:**

```typescript
// Check if PO has previous payments
const hasPreviousPayments = computed(() => {
    return (
        purchaseOrder.value?.payment_status === 'PARTIAL' &&
        purchaseOrder.value?.payments?.length > 0
    );
});

// Filter confirmed payments with invoice
const previousPayments = computed(() => {
    if (!purchaseOrder.value?.payments) return [];
    return purchaseOrder.value.payments.filter(
        (p) => p.status === 'CONFIRMED' && p.supplier_invoice_number,
    );
});
```

**Invoice Selection Handler:**

```typescript
function onSelectPreviousPayment(paymentId: any) {
    const id = Number(paymentId);
    const payment = previousPayments.value.find((p) => p.id === id);

    if (payment) {
        form.value.supplier_invoice_number = payment.supplier_invoice_number;
        form.value.supplier_invoice_date = payment.supplier_invoice_date;
        form.value.supplier_invoice_amount = String(
            payment.supplier_invoice_amount,
        );
        form.value.supplier_invoice_file = null; // Cannot copy file
    }
}
```

**Field Disabling:**

```vue
<Input v-model="form.supplier_invoice_number" :disabled="useExistingInvoice" />
```

### Benefits

1. **Time Saving**: No need to re-enter same invoice details
2. **Data Consistency**: Invoice information stays consistent across payments
3. **User Friendly**: Clear visual indication of partial payment status
4. **Flexibility**: Can still enter new invoice if needed (e.g., revised invoice)

### Use Cases

#### Case 1: Split Payment for Single Invoice

```
Scenario: Supplier invoice Rp 100,000,000, paid in 3 installments

Payment 1: Rp 40,000,000
- Enter invoice: INV-2026-001, Date: Jan 7, Amount: 100,000,000
- Upload invoice PDF

Payment 2: Rp 30,000,000
- Select "Use existing invoice"
- Choose Payment 1 from dropdown
- Invoice details auto-filled ✓

Payment 3: Rp 30,000,000
- Select "Use existing invoice"
- Choose Payment 1 or 2 from dropdown
- Invoice details auto-filled ✓
```

#### Case 2: Multiple Invoices (Revised)

```
Scenario: Supplier sends revised invoice

Payment 1: Rp 50,000,000
- Invoice: INV-2026-001

Payment 2: Rp 50,000,000
- Select "Enter new invoice"
- Invoice: INV-2026-001-REV (revised invoice)
- Upload new PDF
```

### API Changes

**Endpoint:** `GET /api/payments/purchase-orders/{id}/create`

**Response Enhancement:**

```json
{
    "success": true,
    "data": {
        "id": 2,
        "po_number": "PO-202601-0001",
        "payment_status": "PARTIAL",
        "outstanding_amount": 50000000,
        "payments": [
            {
                "id": 1,
                "payment_number": "PAY-202601-0001",
                "supplier_invoice_number": "INV-2026-001",
                "supplier_invoice_date": "2026-01-07",
                "supplier_invoice_amount": 100000000,
                "payment_amount": 50000000,
                "status": "CONFIRMED"
            }
        ]
    }
}
```

### Validation Rules

Same validation rules apply whether using new or existing invoice:

- `supplier_invoice_number`: required, string, max:100
- `supplier_invoice_date`: required, date
- `supplier_invoice_amount`: required, numeric, min:0
- `supplier_invoice_file`: optional when reusing (required for new invoice)

### Database Schema

No database changes required. Uses existing relationships:

- `purchase_orders.payment_status`
- `supplier_payments` table
- Relationships already defined in models

### Files Modified

**Backend:**

- `app/Http/Controllers/Api/PaymentController.php`

**Frontend:**

- `resources/js/pages/Payments/PaymentForm.vue`

**Documentation:**

- `docs/PAYMENT_PARTIAL_INVOICE_REUSE.md` (this file)

### Testing Checklist

- [ ] PO with UNPAID status: No invoice reuse option shown ✓
- [ ] PO with PARTIAL status: Invoice reuse option shown ✓
- [ ] Select existing invoice: Fields auto-filled and disabled ✓
- [ ] Switch back to new invoice: Fields enabled and cleared ✓
- [ ] Submit with existing invoice: Payment created successfully ✓
- [ ] Submit with new invoice: Payment created successfully ✓
- [ ] Dropdown shows only confirmed payments ✓
- [ ] Dropdown shows payments with invoice info only ✓

### Future Enhancements

1. **Invoice File Preview**: Show thumbnail of selected payment's invoice
2. **Download Link**: Add button to download invoice from previous payment
3. **Invoice Matching**: Auto-suggest matching invoices across POs
4. **Invoice Validation**: Warn if total payments exceed invoice amount

---

**Version:** 1.0  
**Date:** January 8, 2026  
**Author:** AI Assistant  
**Status:** ✅ Implemented
