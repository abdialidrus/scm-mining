# Invoice UI Modernization Progress

## 🎯 Objective

Mengubah semua halaman Invoice UI agar konsisten dengan modul lain (Purchase Orders, Goods Receipts, dll)

---

## ✅ Completed Tasks

### 1. **Created Invoice API Service** ✅

**File:** `/resources/js/services/invoiceApi.ts`

**Features:**

- `listInvoices()` - List with filters
- `getInvoice()` - Get single invoice
- `deleteInvoice()` - Delete invoice
- TypeScript types: `InvoiceDto`, `Paginated<T>`

**Pattern:** Follows same structure as `purchaseOrderApi.ts` and `goodsReceiptApi.ts`

### 2. **Modernized Index.vue** ✅

**File:** `/resources/js/Pages/Accounting/Invoices/Index.vue`

**Changes:**

- ✅ Changed from `AuthenticatedLayout` → `AppLayout`
- ✅ Using `StatusBadge` component instead of inline Badge
- ✅ Using proper `Pagination` component
- ✅ API-based with async/await instead of Inertia props
- ✅ Added `BreadcrumbItem` for navigation
- ✅ Using `Multiselect` for filters (consistent with other modules)
- ✅ Simplified UI - removed complex Card layouts
- ✅ Better loading and error states

**UI Structure:**

```
AppLayout
├── Header (title + Create button)
├── Filters (search, status, matching, payment)
├── Table (cleaner, with StatusBadge)
└── Pagination (proper component)
```

**Backup:** Original file saved as `Index-OLD-BACKUP.vue`

---

## 📋 Pending Tasks

### 3. **Modernize Show.vue** ⏳

**Current Status:** Backed up as `Show-OLD-BACKUP.vue`

**Required Changes:**

- [ ] Change `AuthenticatedLayout` → `AppLayout`
- [ ] Use `StatusBadge` for all status displays
- [ ] Add `BreadcrumbItem` navigation
- [ ] Simplify Card layouts
- [ ] Update action buttons styling
- [ ] Add proper loading states
- [ ] Use API service for actions (submit, match, cancel)
- [ ] Add StatusHistoryTable component

### 4. **Modernize Create.vue** ⏳

**Current Status:** Backed up as `Create-OLD-BACKUP.vue`

**Required Changes:**

- [ ] Change to `AppLayout`
- [ ] Simplify form layout
- [ ] Use consistent input components
- [ ] Better error handling
- [ ] Add breadcrumbs

### 5. **Modernize Edit.vue** ⏳

**Current Status:** Backed up as `Edit-OLD-BACKUP.vue`

**Required Changes:**

- [ ] Change to `AppLayout`
- [ ] Simplify form layout
- [ ] Better validation display
- [ ] Add breadcrumbs

### 6. **Modernize Matching.vue** ⏳

**Current Status:** Backed up as `Matching-OLD-BACKUP.vue`

**Required Changes:**

- [ ] Change to `AppLayout`
- [ ] Use StatusBadge
- [ ] Simplify variance display tables
- [ ] Better approve/reject UI
- [ ] Add breadcrumbs

### 7. **Modernize Payments.vue** ⏳

**Current Status:** Backed up as `Payments-OLD-BACKUP.vue`

**Required Changes:**

- [ ] Change to `AppLayout`
- [ ] Use StatusBadge
- [ ] Better payment form
- [ ] Payment history table
- [ ] Add breadcrumbs

---

## 🎨 Design Patterns to Follow

### Layout Structure

```vue
<AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Title</h1>
                <p class="text-sm text-muted-foreground">Description</p>
            </div>
            <Button>Action</Button>
        </div>

        <!-- Content -->
        <!-- ... -->
    </div>
</AppLayout>
```

### Status Display

```vue
<!-- OLD WAY -->
<Badge :variant="status.color as any">{{ status.label }}</Badge>

<!-- NEW WAY -->
<StatusBadge :status="status" />
```

### Pagination

```vue
<Pagination>
    <PaginationContent>
        <Button @click="prevPage"><ChevronLeft /></Button>
        <span>Page {{ page }} of {{ totalPages }}</span>
        <Button @click="nextPage"><ChevronRight /></Button>
    </PaginationContent>
</Pagination>
```

### Multiselect Filters

```vue
<Multiselect
    v-model="status"
    :options="statusOptions"
    track-by="value"
    label="label"
    :searchable="false"
    :show-labels="false"
/>
```

---

## 📦 Components Used

### Core Components

- `AppLayout` - Main layout with sidebar
- `StatusBadge` - Consistent status display
- `Pagination` + `PaginationContent` - Proper pagination
- `Multiselect` - Dropdown filters

### UI Components (Shadcn)

- `Button` from `@/components/ui/button/Button.vue`
- `Input` from `@/components/ui/input/Input.vue`
- `Table` components from `@/components/ui/table`

### Icons (lucide-vue-next)

- Consistent icon usage across all pages

---

## 🔄 Migration Guide

### For Each Page:

1. **Import Changes:**

```typescript
// OLD
import AuthenticatedLayout from '@/layouts/AuthLayout.vue';
import { Badge } from '@/components/ui/badge';

// NEW
import AppLayout from '@/layouts/AppLayout.vue';
import StatusBadge from '@/components/StatusBadge.vue';
```

2. **Layout Change:**

```vue
<!-- OLD -->
<AuthenticatedLayout>
    <Card>
        <CardHeader>...</CardHeader>
        <CardContent>...</CardContent>
    </Card>
</AuthenticatedLayout>

<!-- NEW -->
<AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Simpler structure -->
    </div>
</AppLayout>
```

3. **Status Display:**

```vue
<!-- OLD -->
<Badge :variant="invoice.status.color as any">
    {{ invoice.status.label }}
</Badge>

<!-- NEW -->
<StatusBadge :status="invoice.status" />
```

4. **Add Breadcrumbs:**

```typescript
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/accounting/invoices' },
    { title: invoice.value?.internal_number ?? 'Detail', href: '#' },
];
```

---

## 🚀 Next Steps

### Immediate:

1. Complete Show.vue modernization
2. Update Create.vue and Edit.vue forms
3. Simplify Matching.vue and Payments.vue

### After UI Complete:

1. Test all pages thoroughly
2. Update documentation
3. Check TypeScript errors
4. Verify all features work

---

## 📝 Notes

- All original files backed up with `-OLD-BACKUP.vue` suffix
- Can restore anytime if needed
- API service created for future expansion
- Following exact patterns from Purchase Orders module

---

**Status:** 1/6 pages completed (Index.vue) ✅  
**Next:** Continue with Show.vue, Create.vue, Edit.vue, Matching.vue, Payments.vue
