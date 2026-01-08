# Payment API Documentation

## Overview

API untuk mengelola pembayaran supplier berdasarkan Purchase Orders (PO). Module ini menyederhanakan proses payment tracking tanpa complex invoice matching.

## Authentication

Semua endpoint memerlukan authentication dengan Sanctum token:

```
Authorization: Bearer {your-token}
```

## Role Requirements

- **View**: `finance`, `super_admin`, `gm`, `director`
- **Create/Update/Confirm**: `finance`, `super_admin`

---

## Endpoints

### 1. Get Payment Statistics

Mendapatkan statistik pembayaran untuk dashboard.

**Endpoint:** `GET /api/payments/stats`

**Response:**

```json
{
    "success": true,
    "data": {
        "total_outstanding": 150000000.0,
        "overdue_count": 5,
        "overdue_amount": 50000000.0,
        "this_month_paid": 80000000.0,
        "pending_confirmation": 3
    }
}
```

---

### 2. Get Outstanding Purchase Orders

List PO yang belum/belum lunas dibayar.

**Endpoint:** `GET /api/payments/outstanding`

**Query Parameters:**

- `supplier_id` (optional) - Filter by supplier
- `payment_status` (optional) - Filter: UNPAID, PARTIAL, PAID, OVERDUE
- `overdue_only` (optional) - Boolean, show only overdue
- `search` (optional) - Search PO number or supplier name
- `per_page` (optional) - Items per page (default: 20)

**Response:**

```json
{
    "success": true,
    "data": {
        "purchase_orders": {
            "current_page": 1,
            "data": [
                {
                    "id": 1,
                    "po_number": "PO-202601-0001",
                    "total_amount": 50000000.0,
                    "payment_status": "UNPAID",
                    "payment_term_days": 30,
                    "payment_due_date": "2026-02-08",
                    "total_paid": 0.0,
                    "outstanding_amount": 50000000.0,
                    "supplier": {
                        "id": 1,
                        "name": "PT Supplier ABC",
                        "code": "SUP-001"
                    },
                    "goods_receipts": [],
                    "payments": []
                }
            ],
            "per_page": 20,
            "total": 10
        },
        "stats": {
            "total_outstanding": 150000000.0,
            "overdue_count": 5
        }
    }
}
```

---

### 3. Get Purchase Order Details

Detail PO dengan history pembayaran.

**Endpoint:** `GET /api/payments/purchase-orders/{purchaseOrder}`

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "po_number": "PO-202601-0001",
        "total_amount": 50000000.0,
        "payment_status": "PARTIAL",
        "payment_term_days": 30,
        "payment_due_date": "2026-02-08",
        "total_paid": 20000000.0,
        "outstanding_amount": 30000000.0,
        "supplier": {
            "id": 1,
            "name": "PT Supplier ABC",
            "code": "SUP-001",
            "email": "supplier@abc.com"
        },
        "department": {
            "id": 1,
            "name": "Operations"
        },
        "goods_receipts": [
            {
                "id": 1,
                "gr_number": "GR-202601-0001",
                "receipt_date": "2026-01-05",
                "status": "COMPLETED"
            }
        ],
        "payments": [
            {
                "id": 1,
                "payment_number": "PAY-202601-0001",
                "payment_date": "2026-01-10",
                "payment_amount": 20000000.0,
                "payment_method": "TRANSFER",
                "status": "CONFIRMED",
                "supplier_invoice_number": "INV-2026-001",
                "creator": {
                    "name": "Finance User"
                }
            }
        ],
        "payment_status_histories": [
            {
                "old_status": "UNPAID",
                "new_status": "PARTIAL",
                "changed_at": "2026-01-10T10:30:00",
                "changed_by": {
                    "name": "Finance User"
                }
            }
        ]
    }
}
```

---

### 4. Get Form Data for New Payment

Mendapatkan data PO untuk form pembayaran.

**Endpoint:** `GET /api/payments/purchase-orders/{purchaseOrder}/create`

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "po_number": "PO-202601-0001",
        "total_amount": 50000000.0,
        "outstanding_amount": 30000000.0,
        "supplier": {
            "id": 1,
            "name": "PT Supplier ABC"
        },
        "goods_receipts": [
            {
                "gr_number": "GR-202601-0001",
                "receipt_date": "2026-01-05"
            }
        ]
    }
}
```

---

### 5. Record New Payment

Mencatat pembayaran baru.

**Endpoint:** `POST /api/payments`

**Request Body (multipart/form-data):**

```json
{
    "purchase_order_id": 1,
    "supplier_invoice_number": "INV-2026-001",
    "supplier_invoice_date": "2026-01-08",
    "supplier_invoice_amount": 50000000.0,
    "supplier_invoice_file": "file.pdf",
    "payment_date": "2026-01-10",
    "payment_amount": 20000000.0,
    "payment_method": "TRANSFER",
    "payment_reference": "TRF-20260110-001",
    "payment_proof_file": "proof.pdf",
    "bank_account_from": "BCA 1234567890",
    "bank_account_to": "Mandiri 0987654321",
    "status": "DRAFT",
    "notes": "Pembayaran pertama"
}
```

**Validation Rules:**

- `purchase_order_id`: required, exists in purchase_orders
- `supplier_invoice_number`: required, max 100
- `supplier_invoice_date`: required, valid date
- `supplier_invoice_amount`: required, numeric, min 0
- `supplier_invoice_file`: optional, PDF, max 10MB
- `payment_date`: required, date, before or equal today
- `payment_amount`: required, numeric, min 0.01
- `payment_method`: required, one of: TRANSFER, CASH, CHECK, GIRO
- `payment_proof_file`: optional, PDF/JPG/PNG, max 5MB
- `status`: optional, DRAFT or CONFIRMED

**Response:**

```json
{
    "success": true,
    "message": "Payment recorded successfully",
    "data": {
        "id": 1,
        "payment_number": "PAY-202601-0001",
        "payment_date": "2026-01-10",
        "payment_amount": 20000000.0,
        "status": "DRAFT",
        "purchase_order": {
            "po_number": "PO-202601-0001",
            "payment_status": "PARTIAL"
        }
    }
}
```

---

### 6. Get Payment for Edit

Mendapatkan data payment untuk edit (hanya DRAFT).

**Endpoint:** `GET /api/payments/{payment}/edit`

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "payment_number": "PAY-202601-0001",
        "supplier_invoice_number": "INV-2026-001",
        "payment_amount": 20000000.0,
        "status": "DRAFT",
        "purchase_order": {
            "id": 1,
            "po_number": "PO-202601-0001"
        }
    }
}
```

**Error (if not draft):**

```json
{
    "success": false,
    "message": "Only draft payments can be edited"
}
```

---

### 7. Update Payment

Update payment yang masih DRAFT.

**Endpoint:** `PUT /api/payments/{payment}`

**Request Body:** Same as create endpoint (except purchase_order_id)

**Response:**

```json
{
    "success": true,
    "message": "Payment updated successfully",
    "data": {
        "id": 1,
        "payment_number": "PAY-202601-0001",
        "payment_amount": 25000000.0,
        "status": "DRAFT"
    }
}
```

---

### 8. Confirm Payment

Konfirmasi payment (status DRAFT → CONFIRMED).

**Endpoint:** `POST /api/payments/{payment}/confirm`

**Response:**

```json
{
    "success": true,
    "message": "Payment confirmed successfully",
    "data": {
        "id": 1,
        "payment_number": "PAY-202601-0001",
        "status": "CONFIRMED",
        "approved_by_user_id": 1,
        "approved_at": "2026-01-10T14:30:00",
        "purchase_order": {
            "payment_status": "PARTIAL"
        }
    }
}
```

---

### 9. Cancel Payment

Batalkan payment dengan alasan.

**Endpoint:** `POST /api/payments/{payment}/cancel`

**Request Body:**

```json
{
    "reason": "Invoice number salah"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Payment cancelled successfully",
    "data": {
        "id": 1,
        "payment_number": "PAY-202601-0001",
        "status": "CANCELLED",
        "notes": "Original notes\nCancelled: Invoice number salah"
    }
}
```

---

## Payment Status Flow

```
DRAFT → CONFIRMED → (active)
  ↓
CANCELLED
```

## PO Payment Status

- **UNPAID**: Belum ada pembayaran
- **PARTIAL**: Sudah dibayar sebagian
- **PAID**: Sudah lunas (outstanding_amount = 0)
- **OVERDUE**: Melewati payment_due_date dan belum lunas

## File Upload

**Invoice File:**

- Format: PDF only
- Max size: 10MB
- Stored in: `storage/app/public/invoices/`

**Payment Proof:**

- Format: PDF, JPG, PNG
- Max size: 5MB
- Stored in: `storage/app/public/payment-proofs/`

---

## Example Usage (cURL)

### Get Outstanding POs

```bash
curl -X GET "http://localhost:8000/api/payments/outstanding?overdue_only=1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Record Payment

```bash
curl -X POST "http://localhost:8000/api/payments" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "purchase_order_id=1" \
  -F "supplier_invoice_number=INV-2026-001" \
  -F "supplier_invoice_date=2026-01-08" \
  -F "supplier_invoice_amount=50000000" \
  -F "supplier_invoice_file=@invoice.pdf" \
  -F "payment_date=2026-01-10" \
  -F "payment_amount=20000000" \
  -F "payment_method=TRANSFER" \
  -F "payment_proof_file=@proof.pdf"
```

### Confirm Payment

```bash
curl -X POST "http://localhost:8000/api/payments/1/confirm" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Error Responses

### Validation Error (422)

```json
{
    "message": "The payment amount field is required.",
    "errors": {
        "payment_amount": ["The payment amount field is required."]
    }
}
```

### Authorization Error (403)

```json
{
    "success": false,
    "message": "Only draft payments can be edited"
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Failed to record payment",
    "error": "Database connection error"
}
```
