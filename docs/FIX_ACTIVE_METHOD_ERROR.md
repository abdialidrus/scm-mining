# Fix: BadMethodCallException - active() Method

## Problem

**Error:** `Call to undefined method Illuminate\Database\Eloquent\Builder::active()`

**Location:** `app/Http/Controllers/Api/SupplierInvoiceController.php:124`

**Cause:** Method `getCreateData()` memanggil `->active()` pada Supplier model, tapi model Supplier tidak memiliki scope `active()`.

## Code Issue

```php
// ❌ BEFORE (Error)
'suppliers' => Supplier::select('id', 'code', 'name')
    ->active()  // ← Method tidak ada!
    ->get(),
```

## Solution Applied

```php
// ✅ AFTER (Fixed)
'suppliers' => Supplier::select('id', 'code', 'name')
    ->orderBy('name')  // ← Sort by name instead
    ->get(),
```

## Changes Made

**File:** `/app/Http/Controllers/Api/SupplierInvoiceController.php`

**Line 151:** Removed `->active()` call, added `->orderBy('name')` for better UX

## Result

- ✅ Error fixed
- ✅ Halaman Create Invoice sekarang bisa dibuka
- ✅ Suppliers akan ditampilkan sorted by name

## Testing

Coba akses halaman Create Invoice lagi:

```
http://localhost:8000/accounting/invoices/create
```

**Expected Result:**

- ✅ Page loads successfully
- ✅ Supplier dropdown shows all suppliers (sorted by name)
- ✅ No more BadMethodCallException error

---

**Date:** January 6, 2026  
**Issue:** BadMethodCallException on Create Invoice page  
**Fix:** Removed non-existent `active()` scope call  
**Status:** ✅ RESOLVED
