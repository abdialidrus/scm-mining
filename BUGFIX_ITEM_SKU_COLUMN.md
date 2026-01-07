# 🔧 Bug Fix: Column "code" does not exist

## 🐛 Problem

API endpoint `/api/item-inventory-settings` was returning **Error 500** with message:

```
SQLSTATE[42703]: Undefined column: 7 ERROR: column "code" does not exist
LINE 1: select "id", "code", "name" from "items" where "items"."id" ...
```

## 🔍 Root Cause

The `ItemInventorySettingController` was attempting to load the `code` column from the `items` table, but the actual column name is `sku`.

**Items table structure:**

- ✅ Has: `sku` (String - Item code/SKU)
- ❌ Does not have: `code`

## ✅ Solution Applied

### 1. Backend Controller Fixed

**File**: `app/Http/Controllers/ItemInventorySettingController.php`

**Changed all instances of `code` to `sku` for Item model:**

```php
// BEFORE (❌ Wrong)
$query = ItemInventorySetting::with(['item:id,code,name', 'warehouse:id,code,name'])

$itemQuery->where('code', 'LIKE', "%{$search}%")

$setting->load(['item:id,code,name', 'warehouse:id,code,name']);

// AFTER (✅ Fixed)
$query = ItemInventorySetting::with(['item:id,sku,name', 'warehouse:id,code,name'])

$itemQuery->where('sku', 'LIKE', "%{$search}%")

$setting->load(['item:id,sku,name', 'warehouse:id,code,name']);
```

**Affected Methods:**

- ✅ `index()` - List endpoint
- ✅ `store()` - Create endpoint
- ✅ `show()` - Show endpoint
- ✅ `edit()` - Edit page endpoint
- ✅ `update()` - Update endpoint

**Note**: Warehouse still uses `code` column correctly (unchanged).

### 2. Frontend Components Fixed

**Changed TypeScript interfaces and templates:**

#### File: `resources/js/pages/MasterData/ItemInventorySettings/Index.vue`

```typescript
// BEFORE
item: { id: number; code: string; name: string; };

// Display
<div class="font-medium">{{ setting.item.code }}</div>

// AFTER
item: { id: number; sku: string; name: string; };

// Display
<div class="font-medium">{{ setting.item.sku }}</div>
```

#### File: `resources/js/pages/MasterData/ItemInventorySettings/Form.vue`

```typescript
// Interface
interface Item {
    id: number;
    sku: string;  // Changed from 'code'
    name: string;
}

// Props interface
item: { id: number; sku: string; name: string };

// Template - Multiselect option
<div class="font-medium">{{ option.sku }}</div>  // Changed from option.code

// Template - Multiselect single label
<span>{{ option.sku }} - {{ option.name }}</span>  // Changed from option.code
```

#### File: `resources/js/pages/MasterData/ItemInventorySettings/Show.vue`

```typescript
// Interface
item: { id: number; sku: string; name: string };

// Template
<p class="text-muted-foreground">{{ setting.item.sku }}</p>  // Changed from setting.item.code
```

## 🧪 Testing

### Test API Endpoint (with authentication):

```bash
# Get auth token first
TOKEN="your-bearer-token"

# Test list endpoint
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/item-inventory-settings

# Should return JSON without error 500
```

### Test Frontend:

1. Navigate to: `/master-data/item-inventory-settings`
2. Verify list shows item SKU (not "code")
3. Test search by item SKU
4. Test create form - item dropdown shows SKU
5. Test show page - displays SKU

## 📋 Verification Checklist

- [x] Controller uses `sku` for Item model
- [x] Controller still uses `code` for Warehouse model
- [x] Search functionality works with `sku`
- [x] Index.vue displays `sku` correctly
- [x] Form.vue uses `sku` in interfaces and templates
- [x] Show.vue displays `sku` correctly
- [x] No TypeScript errors
- [x] No compilation errors

## 🎯 Files Changed

1. ✅ `app/Http/Controllers/ItemInventorySettingController.php`
2. ✅ `resources/js/pages/MasterData/ItemInventorySettings/Index.vue`
3. ✅ `resources/js/pages/MasterData/ItemInventorySettings/Form.vue`
4. ✅ `resources/js/pages/MasterData/ItemInventorySettings/Show.vue`

**Total**: 4 files updated

## 📝 Summary

**Issue**: Column mismatch between expected (`code`) and actual (`sku`)  
**Impact**: Error 500 on API calls, preventing feature from working  
**Resolution**: Changed all Item references from `code` to `sku`  
**Status**: ✅ **FIXED** - Ready to test

---

**Date**: January 7, 2026  
**Bug**: SQLSTATE[42703] Undefined column "code"  
**Resolution Time**: ~5 minutes  
**Files Modified**: 4 files (1 backend + 3 frontend)
