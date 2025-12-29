# Serialized Items Implementation

## ✅ Implementation Complete

Implementasi serial number tracking untuk Picking Order telah selesai.

---

## 📋 **Summary**

### **Fitur yang Diimplementasikan:**

1. **Database Schema**
    - Tabel `item_serial_numbers` untuk tracking serial numbers
    - Kolom `is_serialized` pada tabel `items`

2. **API Endpoints**
    - `GET /api/stock/serial-numbers` - Get available serial numbers
    - `GET /api/stock/serial-numbers/{serial}` - Get serial number details

3. **Frontend (Picking Order Form)**
    - Conditional rendering: Qty input vs Serial Number selector
    - Multi-select dropdown untuk serial numbers
    - Auto-calculate qty dari jumlah serial numbers selected
    - Real-time available serials loading

4. **Sample Data**
    - 2 serialized items (Laptop, Tablet)
    - 8 serial numbers (5 laptops, 3 tablets)

---

## 🎯 **How It Works**

### **User Flow for Serialized Items:**

```
1. User selects warehouse → Locations loaded
2. User clicks "Add Item"
3. User selects item "Laptop Dell Latitude 5420" (is_serialized = true)
4. User selects source location "STO - Storage"
5. System detects item is serialized
   → Fetches available serial numbers from API
   → Shows multi-select dropdown instead of qty input
6. User selects serial numbers:
   ☑️ SN-LPT-2025-001
   ☑️ SN-LPT-2025-002
7. Qty auto-calculated to 2
8. User clicks "Save Draft"
9. Backend receives payload with serial_numbers array
```

### **User Flow for Non-Serialized Items:**

```
1-4. Same as above
5. System detects item is NOT serialized
   → Fetches qty_on_hand from stock balance
   → Shows standard qty input field
6. User enters qty: 5.5
7. User clicks "Save Draft"
8. Backend receives payload with qty only
```

---

## 📦 **Sample Data**

### **Serialized Items:**

| ID  | SKU         | Name                      | is_serialized |
| --- | ----------- | ------------------------- | ------------- |
| 4   | ITM-LPT-001 | Laptop Dell Latitude 5420 | ✅ true       |
| 5   | ITM-TAB-001 | Samsung Galaxy Tab A8     | ✅ true       |

### **Serial Numbers (Location: STO - Storage):**

**Laptop (5 units):**

- SN-LPT-2025-001
- SN-LPT-2025-002
- SN-LPT-2025-003
- SN-LPT-2025-004
- SN-LPT-2025-005

**Tablet (3 units):**

- SN-TAB-2025-A01
- SN-TAB-2025-A02
- SN-TAB-2025-A03

---

## 🧪 **Testing Steps**

### **Test Serialized Item:**

1. Navigate to `/picking-orders/create`
2. Select warehouse: **Gudang Utama**
3. Click **Add Item**
4. Select item: **Laptop Dell Latitude 5420**
5. Select location: **STO - Storage**
6. **Verify:** Serial Number dropdown appears (not qty input)
7. **Verify:** Available shows "5" units
8. Select 2 serial numbers from dropdown
9. **Verify:** "2 unit(s) selected" message appears
10. Fill header fields and save

### **Test Non-Serialized Item:**

1. Same steps 1-3
2. Select item: **Engine Oil SAE 15W-40** (non-serialized)
3. Select location: **STO - Storage**
4. **Verify:** Standard qty input appears
5. Enter qty manually
6. Save

---

## 🔧 **Files Modified/Created**

### **Database:**

- ✅ `database/migrations/2025_12_29_000001_create_item_serial_numbers_table.php`
- ✅ `app/Models/ItemSerialNumber.php`
- ✅ `database/seeders/SerializedItemSeeder.php`

### **Backend:**

- ✅ `app/Http/Controllers/Api/ItemSerialNumberController.php`
- ✅ `app/Http/Controllers/Api/ItemController.php` (added `is_serialized` field)
- ✅ `routes/api.php` (added serial number routes)

### **Frontend:**

- ✅ `resources/js/services/serialNumberApi.ts` (new)
- ✅ `resources/js/services/masterDataApi.ts` (added `is_serialized` to ItemDto)
- ✅ `resources/js/pages/picking-orders/Form.vue` (major updates)

---

## 🚀 **Next Steps (Optional)**

### **Phase 2: Goods Receipt**

Implement serial number input saat terima barang:

- Modal untuk input serial numbers di GR form
- Validation: qty received = jumlah serial numbers

### **Phase 3: Backend Validation**

Update `PickingOrderService` untuk:

- Validate serial numbers existence
- Validate serial numbers status = AVAILABLE
- Update serial numbers status to PICKED saat post
- Link serial numbers ke picking order lines

### **Phase 4: Put Away**

Display serial numbers di Put Away form (read-only)

### **Phase 5: Stock Reports**

Show serial number details di stock reports

---

## ⚠️ **Known Limitations (MVP)**

1. **Backend belum handle serial numbers:**
    - `PickingOrderService` belum validate serial numbers
    - Posting belum update serial status to PICKED
2. **Goods Receipt belum support:**
    - Belum bisa input serial numbers saat GR
    - Serial numbers harus di-insert manual via seeder/tinker

3. **No duplicate check:**
    - User bisa select serial number yang sama di line berbeda (frontend)

---

## 💡 **Tips**

- Use warehouse **"Gudang Utama"** untuk testing (has locations)
- Serial numbers tersedia di location **"STO - Storage"**
- Lihat console.log di browser untuk debug API responses
- Run `php artisan tinker` untuk manipulasi serial numbers manual

---

## 🎉 **Status: MVP Complete**

Frontend implementation untuk serialized items **sudah selesai dan siap ditest**.

Backend validation dapat ditambahkan di fase selanjutnya sesuai kebutuhan.
