# SCM Mining - Development Roadmap

**Document Version:** 1.0  
**Date Created:** January 4, 2026  
**Last Updated:** January 4, 2026  
**Status:** Planning

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current Project Status](#current-project-status)
3. [Gap Analysis](#gap-analysis)
4. [Development Phases](#development-phases)
5. [Feature Specifications](#feature-specifications)
6. [Implementation Strategy](#implementation-strategy)
7. [Timeline & Resources](#timeline--resources)
8. [Success Metrics](#success-metrics)

---

## 📊 Executive Summary

This document outlines the strategic development roadmap for the SCM Mining application. The system currently has a **solid foundation** with complete procurement workflows, approval systems, and inventory management. This roadmap focuses on enhancing user experience, management visibility, external integrations, and advanced automation features.

### Key Priorities

1. **Notifications System** - Keep users informed in real-time
2. **Advanced Analytics** - Provide management visibility and insights
3. **Integration Capabilities** - Connect with external systems
4. **Mobile Experience** - Enable on-the-go access
5. **Advanced Features** - Forecasting, budgeting, quality control

---

## 🎯 Current Project Status

### ✅ Completed Modules (Production Ready)

| Module                    | Status      | Completeness | Notes                                  |
| ------------------------- | ----------- | ------------ | -------------------------------------- |
| Purchase Request (PR)     | ✅ Complete | 100%         | Full workflow with approval            |
| Purchase Order (PO)       | ✅ Complete | 100%         | Including PDF export                   |
| Goods Receipt (GR)        | ✅ Complete | 100%         | Full receiving process                 |
| Put Away                  | ✅ Complete | 100%         | Warehouse location management          |
| Picking Order             | ✅ Complete | 100%         | Including serialized items             |
| Approval Workflow         | ✅ Complete | 100%         | Data-driven, multi-tier system         |
| Stock Reports             | ✅ Complete | 100%         | By Location, By Item, Movement History |
| My Approvals Dashboard    | ✅ Complete | 100%         | With badge counter                     |
| Master Data Management    | ✅ Complete | 100%         | Items, Suppliers, Warehouses, UOMs     |
| Role-Based Access Control | ✅ Complete | 100%         | Spatie Permission integration          |

### 🏗️ Technical Foundation

- **Backend:** Laravel 12.x, PHP 8.3+
- **Frontend:** Vue 3 (Composition API), TypeScript, Inertia.js
- **UI Framework:** Tailwind CSS, Shadcn/ui
- **Database:** PostgreSQL with optimized queries
- **Authentication:** Laravel Fortify with Sanctum
- **Authorization:** Spatie Laravel Permission (role-based)
- **State Management:** Vue Composables & Ref
- **Testing:** Pest PHP for backend tests

### 📈 System Capabilities

- ✅ Complete procurement cycle: PR → PO → GR → Put Away → Picking
- ✅ Flexible approval workflows with conditions
- ✅ Real-time stock tracking with ledger-based system
- ✅ Serial number tracking for high-value items
- ✅ Multi-warehouse support
- ✅ Comprehensive audit trails
- ✅ PDF export for documents
- ✅ Role-based menu & feature visibility

---

## 🔍 Gap Analysis

### Critical Gaps (Missing Essential Features)

| Category                     | Current Coverage | Gap                                           |
| ---------------------------- | ---------------- | --------------------------------------------- |
| **Notifications**            | 20%              | No email/push notifications                   |
| **Reporting & Analytics**    | 75%              | Limited executive dashboards (improved!)      |
| **Integration**              | 30%              | No accounting/ERP integration                 |
| **Documentation**            | 50%              | No file attachments                           |
| **Mobile Experience**        | 40%              | No PWA/mobile optimization                    |
| **Warehouse Location UI** ⚠️ | 0%               | **No UI to manage locations (critical gap!)** |

### Functional Gaps by Module

#### Procurement Module (95% Complete)

- ❌ Budget management & tracking
- ❌ Contract management
- ❌ Supplier performance metrics
- ❌ PR to PO conversion optimization

#### Warehouse Module (75% Complete)

- ❌ **Warehouse Location Management UI** (Critical Gap - See Phase 1.4)
- ❌ Location capacity & occupancy tracking
- ❌ Zone-based location hierarchy
- ❌ Location performance metrics
- ❌ Quality inspection process
- ❌ Inventory forecasting
- ❌ Cycle counting
- ❌ ABC analysis (Partial - backend only)

#### Approval Module (95% Complete)

- ❌ Quick approve/reject from dashboard
- ❌ Bulk approval actions
- ❌ Real-time notifications
- ❌ Approval delegation

#### Reporting Module (60% Complete)

- ❌ Executive dashboards
- ❌ KPI tracking
- ❌ Trend analysis
- ❌ Predictive analytics

---

## 🚀 Development Phases

## PHASE 1: Critical Missing Features (Priority: 🔴 HIGH)

**Timeline:** Week 1-3  
**Total Effort:** 28-38 hours  
**Impact:** Immediate user satisfaction improvement & operational efficiency

### 1.1 Email Notifications System ⚡

**Effort Estimate:** 4-6 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Features

- **Approval Notifications:**
    - Email sent when document submitted for approval
    - Email sent when approval assigned to user/role
    - Daily digest of pending approvals

- **Status Change Notifications:**
    - Document approved notification (to submitter)
    - Document rejected notification (to submitter + approvers)
    - Document status changes (SENT, CLOSED, etc.)

- **System Notifications:**
    - Stock level alerts (low stock, out of stock)
    - Overdue approvals reminder
    - Weekly summary reports

#### Technical Implementation

```
app/Notifications/
  ├── Approval/
  │   ├── ApprovalRequiredNotification.php
  │   ├── DocumentApprovedNotification.php
  │   ├── DocumentRejectedNotification.php
  │   └── PendingApprovalReminderNotification.php
  ├── Document/
  │   ├── PurchaseRequestSubmittedNotification.php
  │   ├── PurchaseOrderSubmittedNotification.php
  │   └── GoodsReceiptPostedNotification.php
  └── Inventory/
      ├── LowStockAlertNotification.php
      └── WeeklySummaryNotification.php

resources/views/emails/
  ├── layout.blade.php (base email template)
  ├── approval/
  │   ├── required.blade.php
  │   ├── approved.blade.php
  │   └── rejected.blade.php
  └── inventory/
      └── low-stock.blade.php
```

#### Configuration

```php
// config/notifications.php
return [
    'channels' => [
        'email' => true,
        'database' => true,
        'slack' => false,
    ],
    'queue' => [
        'enabled' => true,
        'connection' => 'database',
        'queue' => 'notifications',
    ],
    'approval_reminder' => [
        'enabled' => true,
        'days_before_escalation' => 3,
        'schedule' => 'daily', // cron: 0 9 * * *
    ],
];
```

#### Integration Points

- Trigger in `ApprovalWorkflowService::initiate()`
- Trigger in `ApprovalWorkflowService::approve()`
- Trigger in `ApprovalWorkflowService::reject()`
- Scheduled command for reminders

---

### 1.2 Advanced Reporting Dashboard 📊

**Effort Estimate:** 12-16 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### A. Procurement Analytics Dashboard

**Route:** `/reports/procurement`

**Metrics:**

1. **Overview Cards**
    - Total PO value (current month)
    - Total POs created (current month)
    - Average approval time
    - Pending approvals count

2. **Charts & Visualizations**
    - PO value by month (last 12 months) - Line chart
    - Top 10 suppliers by value - Bar chart
    - Spending by department - Pie chart
    - PR to PO conversion rate - Gauge chart
    - Approval bottleneck analysis - Funnel chart

3. **Data Tables**
    - Recent POs with status
    - Pending approvals (urgent first)
    - Overdue POs

**Filters:**

- Date range selector
- Department filter
- Supplier filter
- Status filter

#### B. Inventory Analytics Dashboard

**Route:** `/reports/inventory`

**Metrics:**

1. **Overview Cards**
    - Total stock value
    - Total items in stock
    - Low stock items count
    - Out of stock items count

2. **Charts & Visualizations**
    - Stock value by location - Bar chart
    - Stock aging analysis - Stacked bar chart
    - Top 10 items by value - Bar chart
    - Stock movement trend (last 30 days) - Line chart
    - ABC analysis - Scatter plot

3. **Data Tables**
    - Low stock alerts (with reorder suggestions)
    - Slow-moving items (no movement > 90 days)
    - Fast-moving items (high turnover)
    - Dead stock identification

**Filters:**

- Warehouse filter
- Location filter
- Item category filter
- Date range for movement analysis

#### C. Operational Metrics Dashboard

**Route:** `/reports/operations`

**Metrics:**

1. **Cycle Time Analysis**
    - Average PR to PO time
    - Average PO to GR time
    - Average GR to Put Away time
    - Average Picking Order fulfillment time

2. **Efficiency Metrics**
    - Document processing velocity
    - Approval turnaround time by step
    - Warehouse utilization %
    - Stock turnover ratio

3. **Quality Metrics**
    - Rejection rate by approver
    - Error rate in GR
    - Accuracy rate in Picking
    - Serial number tracking compliance

#### Technical Stack

```typescript
// Frontend
- Chart library: ApexCharts (recommended) or Chart.js
- Date picker: Vue Datepicker
- Export: vue3-json-excel

// Backend
- Export to Excel: Laravel Excel (Maatwebsite)
- Complex queries: Eloquent with DB::raw for aggregations
- Caching: Redis for dashboard data (5-15 min TTL)
```

#### API Endpoints

```
GET /api/reports/procurement/overview
GET /api/reports/procurement/po-trend
GET /api/reports/procurement/top-suppliers
GET /api/reports/procurement/spending-by-department
GET /api/reports/procurement/export (Excel download)

GET /api/reports/inventory/overview
GET /api/reports/inventory/stock-value-by-location
GET /api/reports/inventory/aging-analysis
GET /api/reports/inventory/abc-analysis
GET /api/reports/inventory/export

GET /api/reports/operations/cycle-time
GET /api/reports/operations/efficiency-metrics
```

#### Files to Create

```
app/Http/Controllers/Api/Reports/
  ├── ProcurementReportController.php
  ├── InventoryReportController.php
  └── OperationsReportController.php

app/Services/Reports/
  ├── ProcurementReportService.php
  ├── InventoryReportService.php
  └── ReportExportService.php

resources/js/pages/reports/
  ├── ProcurementDashboard.vue
  ├── InventoryDashboard.vue
  └── OperationsDashboard.vue

resources/js/components/charts/
  ├── LineChart.vue
  ├── BarChart.vue
  ├── PieChart.vue
  └── GaugeChart.vue

routes/reports.php
```

---

### 1.4 Warehouse Location Management UI 📦

**Effort Estimate:** 14-18 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Current State

**✅ Backend Complete:**

- Database schema exists (`warehouse_locations` table)
- API endpoints functional (`WarehouseLocationController`)
- Model relationships working (`WarehouseLocation`, `Warehouse`)
- Business logic implemented (types: RECEIVING, STORAGE)
- Used internally by Put Away, Picking Order, Stock Reports

**❌ Frontend Missing:**

- No UI to manage warehouse locations
- No way to create/edit/delete locations
- No visibility into location hierarchy
- No location utilization dashboard
- Manual database entry required for new locations

**Impact:**
Users must ask developers to create new storage locations via database seeds. This is inefficient and blocks warehouse operations.

---

#### Features to Implement

**A. Location Master Data CRUD**

**Route:** `/master-data/warehouse-locations`

**Permissions:** `super_admin`, `warehouse`, `warehouse_supervisor`

**Features:**

1. **List All Locations**
    - Table with: Code, Name, Type, Warehouse, Active status
    - Filter by: Warehouse, Type (RECEIVING/STORAGE), Active status
    - Search by: Code, Name
    - Pagination
    - Click row to view details

2. **Create Location**
    - Form fields:
        - Warehouse (dropdown - required)
        - Parent Location (dropdown - optional, for hierarchy)
        - Type (radio: RECEIVING / STORAGE - required)
        - Code (text - required, unique per warehouse)
        - Name (text - required)
        - Capacity (number - optional, for future capacity management)
        - Max Weight (number - optional)
        - Is Default (checkbox)
        - Is Active (checkbox - default true)
        - Notes (textarea)
    - Validation:
        - Only one default RECEIVING per warehouse (enforced by DB)
        - Code must be unique within warehouse
        - Parent location must be in same warehouse

3. **Edit Location**
    - Same form as create
    - Warning if location has stock (show current qty)
    - Disable warehouse change if location has stock

4. **View Location Details**
    - Basic info card
    - Current stock summary (items count, total qty, total value)
    - Stock by item table
    - Recent movements (last 20)
    - Child locations (if hierarchical)
    - Quick actions: Edit, Deactivate/Activate

5. **Delete/Deactivate Location**
    - Soft delete by setting `is_active = false`
    - Block if location has stock balance > 0
    - Show warning with affected documents

**B. Location Hierarchy Visualization**

**Route:** `/warehouse/location-tree?warehouse_id=X`

**Features:**

1. **Tree View**
    - Visual hierarchy (parent → children)
    - Expand/collapse nodes
    - Color coding by type
    - Icons for RECEIVING vs STORAGE

2. **Quick Stats per Node**
    - Items stored: X
    - Current utilization: Y%
    - Available capacity: Z

3. **Drag & Drop (Phase 2)**
    - Reorganize hierarchy
    - Change parent location
    - Validation on drop

**C. Location Utilization Dashboard**

**Route:** `/warehouse/location-utilization`

**Metrics:**

1. **Overview Cards**
    - Total locations
    - Active locations
    - Empty locations (no stock)
    - Over-capacity locations (if capacity defined)

2. **Charts**
    - Top 10 locations by item count (bar chart)
    - Top 10 locations by stock value (bar chart)
    - Location type distribution (pie chart)
    - Utilization heat map (color-coded warehouse floor plan - Phase 3)

3. **Data Tables**
    - Underutilized locations (< 30% capacity)
    - Over-stocked locations (> 90% capacity)
    - Empty locations (candidates for deactivation)

**D. Stock Movement by Location Report**

**Existing in:** `/stock-reports` (already implemented)

**Enhancement:**

- Add location filter (already exists ✅)
- Add location-to-location transfer report
- Export to Excel

---

#### Technical Implementation

**1. Frontend Files to Create**

```
resources/js/pages/master-data/warehouse-locations/
  ├── Index.vue          (List all locations)
  ├── Form.vue           (Create/Edit location)
  ├── Show.vue           (View location details)
  └── Tree.vue           (Hierarchical tree view)

resources/js/pages/warehouse/
  ├── LocationUtilization.vue  (Dashboard)
  └── LocationTransfers.vue    (Transfer report)

resources/js/components/warehouse/
  ├── LocationCard.vue         (Reusable location display)
  ├── LocationSelector.vue     (Dropdown with search)
  └── LocationTreeNode.vue     (Tree node component)
```

**2. Backend Files to Create/Update**

```
app/Http/Controllers/Api/WarehouseLocationController.php (✅ exists, may need updates)
app/Http/Requests/Api/WarehouseLocation/
  ├── StoreWarehouseLocationRequest.php  (NEW)
  └── UpdateWarehouseLocationRequest.php (NEW)

app/Services/Warehouse/
  └── LocationService.php  (NEW - business logic)

app/Policies/WarehouseLocationPolicy.php (✅ exists)

routes/master_data.php  (ADD location routes)
routes/api.php          (UPDATE location API)
```

**3. Database Schema Enhancement**

Current schema is sufficient, but add these optional columns for Phase 2:

```sql
ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS capacity DECIMAL(15,2);
ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS max_weight DECIMAL(15,2);
ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE warehouse_locations ADD COLUMN IF NOT EXISTS floor_plan_coordinates JSONB;
```

**4. API Endpoints**

```
# Master Data CRUD
GET    /api/warehouse-locations              (✅ exists)
POST   /api/warehouse-locations              (NEW)
GET    /api/warehouse-locations/{id}         (NEW)
PUT    /api/warehouse-locations/{id}         (NEW)
DELETE /api/warehouse-locations/{id}         (NEW)

# Warehouse-specific locations (already used by forms)
GET    /api/warehouses/{id}/locations        (✅ exists)

# Location utilization
GET    /api/warehouse-locations/{id}/stock-summary  (NEW)
GET    /api/warehouse-locations/{id}/movements      (NEW)
GET    /api/warehouse-locations/utilization         (NEW)

# Hierarchy
GET    /api/warehouses/{id}/location-tree          (NEW)
```

**5. Integration Points**

**Existing Usage (No Changes Needed):**

- ✅ Put Away Form: Uses locations for destination selection
- ✅ Picking Order Form: Uses locations for source selection
- ✅ Stock Reports: Filters by location
- ✅ GR Posting: Auto-creates stock in receiving location

**New Integrations:**

- Warehouse Setup Wizard: Create locations during warehouse creation
- Stock Transfer Document: Move between locations (Phase 2)
- Cycle Count: Schedule counts per location (Phase 2)

---

#### UI/UX Mockup

**Location List Page:**

```
┌────────────────────────────────────────────────────────────┐
│ Warehouse Locations                         [+ Create]     │
├────────────────────────────────────────────────────────────┤
│ Filters:                                                   │
│ [Warehouse: All ▼]  [Type: All ▼]  [Status: Active ▼]    │
│ [Search by code or name...]                                │
├────────────────────────────────────────────────────────────┤
│ Code    │ Name              │ Type      │ Warehouse │ ✓   │
├─────────┼───────────────────┼───────────┼───────────┼─────┤
│ RCV-01  │ Main Receiving    │ 🟦 RCV   │ WH-MAIN   │ ✓  │
│ ZONE-A  │ Spare Parts Zone  │ 🟩 STO   │ WH-MAIN   │ ✓  │
│ ZONE-B  │ Consumables Zone  │ 🟩 STO   │ WH-MAIN   │ ✓  │
│ ZONE-C  │ PPE & Safety      │ 🟩 STO   │ WH-MAIN   │ ✓  │
│ QUARANTINE│ Quarantine Area │ 🟩 STO   │ WH-MAIN   │ ✓  │
└────────────────────────────────────────────────────────────┘
```

**Location Detail Page:**

```
┌────────────────────────────────────────────────────────────┐
│ ← Back                ZONE-A                    [Edit] [⚙] │
├────────────────────────────────────────────────────────────┤
│ 📦 Spare Parts Zone                                        │
│ WH-MAIN • STORAGE • Active                                 │
├────────────────────────────────────────────────────────────┤
│ Current Stock Summary                                      │
│ ┌───────────┬───────────┬───────────────┐                │
│ │ 45 Items  │ 150 Units │ Rp 1.2M Value │                │
│ └───────────┴───────────┴───────────────┘                │
├────────────────────────────────────────────────────────────┤
│ Stock by Item                                              │
│ Item SKU     │ Item Name        │ Qty    │ UOM │ Value   │
│ SPR-001      │ Bearing XYZ      │ 10     │ PCS │ 50K     │
│ SPR-002      │ Seal Kit ABC     │ 25     │ SET │ 120K    │
│ ...                                                        │
├────────────────────────────────────────────────────────────┤
│ Recent Movements (Last 20)                                 │
│ Date       │ Type        │ Item      │ Qty  │ Reference  │
│ 2026-01-05 │ PUT_AWAY    │ SPR-001   │ +5   │ PA-123     │
│ 2026-01-04 │ PICKING     │ SPR-002   │ -3   │ PKO-456    │
└────────────────────────────────────────────────────────────┘
```

**Location Tree View:**

```
┌────────────────────────────────────────────────────────────┐
│ Warehouse: [WH-MAIN ▼]                                     │
├────────────────────────────────────────────────────────────┤
│ 📦 Main Warehouse                                          │
│   ├─ 🟦 RCV-01: Main Receiving (5 items, 10 units)       │
│   └─ 🟩 Storage Zones                                      │
│       ├─ ZONE-A: Spare Parts (45 items, 150 units)       │
│       │   ├─ ZONE-A-1: Heavy Equipment Parts             │
│       │   └─ ZONE-A-2: Light Equipment Parts             │
│       ├─ ZONE-B: Consumables (30 items, 500 units)       │
│       └─ ZONE-C: PPE & Safety (20 items, 200 units)      │
└────────────────────────────────────────────────────────────┘
```

---

#### Implementation Steps

**Phase 1.4A: Basic CRUD (8-10 hours)** ✅ COMPLETE

1. ✅ Backend: Create request validation classes (1h)
2. ✅ Backend: Update WarehouseLocationController with CRUD methods (2h)
3. ✅ Backend: Create LocationService for business logic (1h)
4. ✅ Frontend: Create Index.vue (list page) (2h)
5. ✅ Frontend: Create Form.vue (create/edit) (2h)
6. ✅ Frontend: Add routes in master_data.php (0.5h)
7. ✅ Testing: Manual testing of CRUD operations (1h)

**Phase 1.4B: Location Details & Stock View (4-5 hours)** ✅ COMPLETE

1. ✅ Backend: Add stock summary API endpoint (1h)
2. ✅ Backend: Add recent movements API endpoint (1h)
3. ✅ Backend: Add stock by item API endpoint (1h)
4. ✅ Frontend: Create Show.vue (detail page) (2h)
5. ✅ Frontend: Add stock by item table with search (1h)
6. ✅ Testing: Verify stock display accuracy (1h)

**Phase 1.4C: Location Hierarchy (Optional - Phase 2)**

- Tree view visualization
- Drag & drop reorganization
- Parent-child relationship management

**Phase 1.4D: Utilization Dashboard (Optional - Phase 2)**

- Capacity tracking
- Utilization metrics
- Heat map visualization

---

#### Business Value

**Immediate Benefits:**

- ✅ Self-service location management (no developer needed)
- ✅ Better visibility into warehouse organization
- ✅ Faster warehouse setup for new operations
- ✅ Foundation for advanced WMS features

**Long-term Value:**

- Capacity planning
- Location optimization
- Warehouse efficiency metrics
- Integration with WMS/ERP systems

---

#### Dependencies

**Prerequisites:**

- ✅ Warehouse model (exists)
- ✅ Stock balance tracking (exists)
- ✅ Permission system (exists)

**Blocks (if not implemented):**

- Future: Stock transfers between locations
- Future: Cycle counting
- Future: Location-based picking optimization

---

### 1.5 Document Attachments 📎

**Effort Estimate:** 6-8 hours  
**Business Value:** ⭐⭐⭐⭐

#### Features

- Upload multiple files to PR/PO/GR
- Supported types: PDF, Images (JPG, PNG), Excel, Word
- File size limit: 10MB per file
- Preview for images and PDFs
- Download individual file or all as ZIP
- Delete attachments (with permission check)
- Audit trail for uploads/downloads/deletes

#### Database Schema

```sql
CREATE TABLE document_attachments (
    id BIGSERIAL PRIMARY KEY,
    attachable_type VARCHAR(255) NOT NULL, -- App\Models\PurchaseRequest, etc.
    attachable_id BIGINT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL, -- in bytes
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by_user_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
);

CREATE INDEX idx_attachable ON document_attachments(attachable_type, attachable_id);
```

#### API Endpoints

```
POST   /api/{module}/{id}/attachments        # Upload
GET    /api/{module}/{id}/attachments        # List
GET    /api/{module}/{id}/attachments/{aid}  # Download
DELETE /api/{module}/{id}/attachments/{aid}  # Delete
GET    /api/{module}/{id}/attachments/zip    # Download all as ZIP
```

#### Storage Strategy

```php
// config/filesystems.php
'attachments' => [
    'driver' => 'local',
    'root' => storage_path('app/attachments'),
    'visibility' => 'private',
],

// Organized by module and date
// storage/app/attachments/purchase-requests/2026/01/pr-123/file.pdf
```

#### Frontend Component

```vue
<AttachmentUploader
    :attachable-type="'purchase_request'"
    :attachable-id="pr.id"
    :can-upload="abilities.can('update', pr)"
    :can-delete="abilities.can('delete', pr)"
    @uploaded="loadAttachments"
/>
```

---

## PHASE 2: High-Value Features (Priority: 🟠 MEDIUM-HIGH)

**Timeline:** Week 4-8  
**Total Effort:** 72-98 hours  
**Impact:** Significant business value & competitive advantage

### 2.1 Advanced Warehouse Management 🏭

**Effort Estimate:** 20-30 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Sub-Features

**A. Location Capacity Management**

**Purpose:** Track and optimize warehouse space utilization

**Features:**

- Define capacity per location (volume, weight, pallet count)
- Real-time capacity utilization percentage
- Alerts when nearing capacity (80%, 90%, 100%)
- Capacity planning tools
- Suggest optimal stock placement

**Database Schema:**

```sql
ALTER TABLE warehouse_locations ADD COLUMN:
  - capacity_volume DECIMAL(15,2)      -- cubic meters
  - capacity_weight DECIMAL(15,2)      -- kg
  - capacity_pallet_count INT          -- number of pallets
  - current_volume_used DECIMAL(15,2)
  - current_weight_used DECIMAL(15,2)
  - current_pallet_count INT
```

**B. Zone-Based Location Hierarchy**

**Purpose:** Organize warehouse into logical zones and sub-zones

**Features:**

- Multi-level hierarchy: Warehouse → Zone → Aisle → Rack → Shelf → Bin
- Flexible depth (2-6 levels)
- Zone types: Fast-moving, Slow-moving, Bulk, Hazmat, Cold storage
- Zone-based picking strategies
- Visual warehouse map

**Example Hierarchy:**

```
WH-MAIN
  └─ ZONE-A (Heavy Equipment)
      ├─ AISLE-1
      │   ├─ RACK-A1
      │   │   ├─ SHELF-1
      │   │   └─ SHELF-2
      │   └─ RACK-A2
      └─ AISLE-2
```

**Database Enhancement:**

```sql
ALTER TABLE warehouse_locations ADD COLUMN:
  - level INT                          -- depth in hierarchy (1-6)
  - zone_type VARCHAR(50)              -- fast_moving, bulk, hazmat, etc.
  - aisle VARCHAR(10)
  - rack VARCHAR(10)
  - shelf VARCHAR(10)
  - bin VARCHAR(10)
  - floor_plan_x INT                   -- X coordinate on map
  - floor_plan_y INT                   -- Y coordinate on map
```

**C. Stock Transfer Between Locations**

**Purpose:** Move stock within warehouse without external transaction

**Features:**

- Create transfer document (similar to Put Away)
- Transfer types:
    - Relocation (optimization)
    - Consolidation (merge bins)
    - Replenishment (fast-moving → picking area)
- Approval workflow (optional, for sensitive items)
- Barcode scanning support
- Batch transfer

**New Document Type:**

```
Stock Transfer (ST)
  - ST Number: ST-YYYYMM-XXXX
  - Status: DRAFT, POSTED, CANCELLED
  - Transfer Type: RELOCATION, CONSOLIDATION, REPLENISHMENT
  - From Location
  - To Location
  - Lines: Item, Qty, UOM, Reason
```

**Database Schema:**

```sql
CREATE TABLE stock_transfers (
  id BIGSERIAL PRIMARY KEY,
  st_number VARCHAR(50) UNIQUE,
  warehouse_id BIGINT REFERENCES warehouses(id),
  transfer_type VARCHAR(30),
  status VARCHAR(30),
  transfer_at TIMESTAMP,
  created_by_user_id BIGINT,
  posted_at TIMESTAMP,
  posted_by_user_id BIGINT,
  reason TEXT,
  remarks TEXT
);

CREATE TABLE stock_transfer_lines (
  id BIGSERIAL PRIMARY KEY,
  stock_transfer_id BIGINT REFERENCES stock_transfers(id),
  item_id BIGINT REFERENCES items(id),
  uom_id BIGINT REFERENCES uoms(id),
  source_location_id BIGINT REFERENCES warehouse_locations(id),
  destination_location_id BIGINT REFERENCES warehouse_locations(id),
  qty DECIMAL(18,4),
  remarks TEXT
);
```

**D. Location Performance Metrics**

**Purpose:** Measure and optimize location efficiency

**Metrics:**

1. **Turnover Rate per Location**
    - Items picked per day
    - Stock age in location
    - Slow-moving vs fast-moving classification

2. **Picking Efficiency**
    - Average picking time per location
    - Pick accuracy rate
    - Travel distance to location

3. **Space Efficiency**
    - Utilization percentage
    - Empty days (location idle)
    - Items per square meter

4. **Location Quality Score**
    - Damage rate (items damaged in location)
    - Accuracy rate (cycle count accuracy)
    - Accessibility score

**Dashboard:**

```
┌────────────────────────────────────────────────────────────┐
│ Location Performance Dashboard                             │
├────────────────────────────────────────────────────────────┤
│ Top Performers                   │ Bottom Performers       │
│ ┌────────────────────────────┐   │ ┌───────────────────┐  │
│ │ ZONE-A: 95% efficient      │   │ │ ZONE-D: 45%      │  │
│ │ • 50 picks/day             │   │ │ • 5 picks/day     │  │
│ │ • 98% accuracy             │   │ │ • 80% accuracy    │  │
│ └────────────────────────────┘   │ └───────────────────┘  │
├────────────────────────────────────────────────────────────┤
│ Location Utilization Heatmap                               │
│ [Interactive warehouse floor plan with color-coded zones]  │
└────────────────────────────────────────────────────────────┘
```

**E. Cycle Counting**

**Purpose:** Regular inventory verification without full stocktake

**Features:**

- Cycle count schedule (daily, weekly, monthly)
- Location-based counting (count one zone at a time)
- ABC classification priority (count A items more frequently)
- Mobile-friendly counting interface
- Variance reporting and adjustment
- Automatic stock adjustment on approval

**Workflow:**

1. System generates cycle count task
2. Warehouse staff counts physical stock
3. Enter count into system
4. System calculates variance (system vs actual)
5. If variance > threshold, require approval
6. On approval, create stock adjustment

**Database Schema:**

```sql
CREATE TABLE cycle_counts (
  id BIGSERIAL PRIMARY KEY,
  count_number VARCHAR(50) UNIQUE,
  warehouse_id BIGINT,
  location_id BIGINT,
  count_date DATE,
  status VARCHAR(30), -- SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED
  assigned_to_user_id BIGINT,
  completed_at TIMESTAMP,
  approved_by_user_id BIGINT,
  approved_at TIMESTAMP
);

CREATE TABLE cycle_count_lines (
  id BIGSERIAL PRIMARY KEY,
  cycle_count_id BIGINT REFERENCES cycle_counts(id),
  item_id BIGINT,
  uom_id BIGINT,
  system_qty DECIMAL(18,4),      -- qty per system
  counted_qty DECIMAL(18,4),      -- qty counted
  variance_qty DECIMAL(18,4),     -- difference
  variance_value DECIMAL(20,2),   -- financial impact
  reason TEXT,
  notes TEXT
);
```

---

### 2.2 Budget Management Module 💰

**Effort Estimate:** 16-20 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Features

**A. Budget Planning**

- Create annual budgets by department
- Allocate budgets by category (Materials, Services, Capex, etc.)
- Budget approval workflow
- Budget revision & reallocation
- Multi-year budget comparison

**B. Budget Tracking**

- Real-time budget vs actual spending
- Budget utilization % per department/category
- Committed amount (pending POs)
- Available balance calculation
- Forecast to year-end

**C. Budget Control**

- Block PR submission if over budget (configurable)
- Warning alerts at 80%, 90%, 100% utilization
- Budget approval required for over-budget PRs
- Budget transfer between departments (with approval)

**D. Reporting**

- Budget utilization dashboard
- Variance analysis (budget vs actual)
- Trend analysis by department
- Top spending categories
- Budget forecasting

#### Database Schema

```sql
CREATE TABLE budgets (
    id BIGSERIAL PRIMARY KEY,
    fiscal_year INT NOT NULL,
    department_id BIGINT NOT NULL,
    category VARCHAR(100) NOT NULL, -- MATERIALS, SERVICES, CAPEX, etc.
    allocated_amount DECIMAL(20,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'DRAFT', -- DRAFT, APPROVED, ACTIVE, CLOSED
    approved_by_user_id BIGINT,
    approved_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE budget_transactions (
    id BIGSERIAL PRIMARY KEY,
    budget_id BIGINT NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- COMMITMENT, ACTUAL, ADJUSTMENT
    source_type VARCHAR(255), -- App\Models\PurchaseRequest, etc.
    source_id BIGINT,
    amount DECIMAL(20,2) NOT NULL,
    description TEXT,
    created_by_user_id BIGINT,
    created_at TIMESTAMP
);
```

#### Integration Points

- Check budget before PR submission
- Create commitment transaction on PR submit
- Update commitment on PR to PO conversion
- Create actual transaction on PO approval
- Release commitment on PR/PO cancellation

---

### 2.2 Purchase Request to PO Conversion Enhancement 🔄

**Effort Estimate:** 6-8 hours  
**Business Value:** ⭐⭐⭐⭐

#### Current State

- Manual selection of PRs in PO create form
- One PO per batch

#### Enhanced Features

**A. Bulk Conversion**

- Select multiple PRs from PR list
- Click "Convert to PO" bulk action
- System auto-groups by supplier
- Preview combined POs before creation
- Create multiple POs in one action

**B. Smart Grouping**

- Auto-group PRs by supplier
- Show summary: # of PRs, total value, item count
- Option to split/merge groups manually
- Exclude certain PR lines from conversion

**C. Conversion Preview**

```
┌─────────────────────────────────────────────┐
│ Conversion Summary                          │
├─────────────────────────────────────────────┤
│ Selected PRs: 5                             │
│ Will create: 3 POs                          │
│                                             │
│ PO 1: Supplier A (PR-001, PR-003)          │
│   - 10 lines, Total: $15,000               │
│                                             │
│ PO 2: Supplier B (PR-002)                  │
│   - 5 lines, Total: $8,500                 │
│                                             │
│ PO 3: Supplier C (PR-004, PR-005)          │
│   - 8 lines, Total: $12,200                │
└─────────────────────────────────────────────┘
```

**D. Better Traceability**

- PR detail shows linked PO number(s)
- PO detail shows all source PRs (already exists)
- Highlight converted lines in PR detail
- Track partial conversions (some lines converted, some not)

#### UI Improvements

```vue
<!-- PR Index page -->
<template>
    <div>
        <!-- Bulk action bar -->
        <div v-if="selectedPRs.length > 0" class="bulk-actions">
            <Button @click="showConvertDialog = true">
                Convert to PO ({{ selectedPRs.length }} selected)
            </Button>
        </div>

        <!-- PR table with checkboxes -->
        <Table>
            <TableRow v-for="pr in prs" :key="pr.id">
                <TableCell>
                    <Checkbox v-model="selectedPRs" :value="pr.id" />
                </TableCell>
                <!-- ...other columns -->
            </TableRow>
        </Table>
    </div>
</template>
```

---

### 2.3 Goods Receipt - Quality Inspection Module ✅

**Effort Estimate:** 10-12 hours  
**Business Value:** ⭐⭐⭐⭐

#### Features

**A. Inspection Configuration**

- Define inspection checklist per item or category
- Configurable inspection criteria (visual, measurement, testing)
- Pass/Fail/Conditional acceptance rules
- Required photo documentation
- Inspector role assignment

**B. Inspection Process**

- Inspect each GR line individually
- Record inspection results
- Upload photos (damage, quality issues)
- Accept, Reject, or Partial accept
- Generate inspection report

**C. Rejection Handling**

- Reject to supplier (create return document)
- Reject to quarantine location
- Notify procurement & supplier
- Track rejected quantity vs accepted

**D. Reporting**

- Inspection history per item/supplier
- Rejection rate by supplier
- Quality trend analysis
- Photo gallery of issues

#### Database Schema

```sql
CREATE TABLE inspection_checklists (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applicable_to VARCHAR(50), -- ITEM, CATEGORY, ALL
    checklist_items JSONB NOT NULL, -- [{criterion, method, pass_criteria}]
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE goods_receipt_inspections (
    id BIGSERIAL PRIMARY KEY,
    goods_receipt_line_id BIGINT NOT NULL,
    inspector_user_id BIGINT NOT NULL,
    inspection_checklist_id BIGINT,
    inspection_result VARCHAR(50) NOT NULL, -- PASS, FAIL, CONDITIONAL
    inspected_quantity DECIMAL(15,4) NOT NULL,
    accepted_quantity DECIMAL(15,4) NOT NULL,
    rejected_quantity DECIMAL(15,4) NOT NULL,
    rejection_reason TEXT,
    inspection_notes TEXT,
    inspection_photos JSONB, -- [{path, description}]
    inspected_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

### 2.4 Vendor Portal 🏢

**Effort Estimate:** 20-30 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Features

**A. Vendor Authentication**

- Separate login for suppliers
- Email invitation with temporary password
- Supplier can manage their profile
- Multiple users per supplier account

**B. PO Management**

- View all POs sent to them
- Filter by status (SENT, IN_PROGRESS, COMPLETED)
- Download PO PDF
- Acknowledge PO receipt
- Reject PO with reason (if needed)
- Update delivery schedule

**C. Communication**

- Message thread per PO
- Upload documents (invoice, delivery note, etc.)
- Request changes or clarifications
- Track message history

**D. Invoice & Delivery**

- Upload invoice against PO
- Upload delivery note
- Track payment status (if integrated)
- View GR history

**E. Performance Dashboard**

- On-time delivery rate
- Quality acceptance rate
- PO fulfillment rate
- Average lead time

#### Technical Implementation

```
routes/vendor.php (separate from main app)

app/Http/Controllers/Vendor/
  ├── VendorAuthController.php
  ├── VendorPurchaseOrderController.php
  ├── VendorInvoiceController.php
  └── VendorDashboardController.php

resources/js/pages/vendor/
  ├── Login.vue
  ├── Dashboard.vue
  ├── PurchaseOrders.vue
  └── PODetail.vue

resources/js/layouts/VendorLayout.vue
```

#### Security Considerations

- Vendors can only see their own POs
- Rate limiting for vendor API
- Separate database guards for vendor users
- Audit log for all vendor actions

---

## PHASE 3: UX & Productivity (Priority: 🟡 MEDIUM)

**Timeline:** Week 7-10  
**Total Effort:** 18-24 hours  
**Impact:** Improved daily operations efficiency

### 3.1 Advanced Search & Filters 🔍

**Effort Estimate:** 8-10 hours  
**Business Value:** ⭐⭐⭐⭐

#### Features

**A. Global Search**

- Search across all modules (PR, PO, GR, Items, Suppliers)
- Type-ahead suggestions
- Recent searches
- Search by multiple criteria

**B. Advanced Filter Builder**

- AND/OR condition builder
- Multiple field filters
- Date range picker
- Amount range slider
- Status multi-select

**C. Saved Filters**

- Save custom filter combinations
- Name and share filters with team
- Quick access to saved filters
- Default filter per user

**D. Quick Filters**

- "My documents"
- "Urgent" (near deadline)
- "Overdue"
- "Pending my approval"
- "Recent" (last 7 days)

---

### 3.2 Approval Workflow - Quick Actions ⚡

**Effort Estimate:** 4-6 hours  
**Business Value:** ⭐⭐⭐⭐

#### Features

- Approve/reject from My Approvals dashboard (without opening detail)
- Bulk approve checkbox selection
- Quick comment templates
- Keyboard shortcuts (A = approve, R = reject)
- Real-time updates with WebSocket

#### UI Enhancement

```vue
<template>
    <div class="approval-item">
        <div class="quick-actions">
            <Button size="sm" @click="quickApprove(item.id)">
                ✓ Approve
            </Button>
            <Button
                size="sm"
                variant="destructive"
                @click="showRejectDialog(item.id)"
            >
                ✗ Reject
            </Button>
        </div>
    </div>
</template>
```

---

### 3.3 Scheduled Jobs & Automation 🤖

**Effort Estimate:** 8-10 hours  
**Business Value:** ⭐⭐⭐⭐

#### Features

**A. Approval Escalation**

- Auto-remind approvers after X days
- Escalate to next level if no response
- Daily digest email of pending approvals

**B. Auto-close Old Documents**

- Auto-close POs older than X months
- Auto-archive completed documents
- Cleanup draft documents > 30 days old

**C. Stock Alerts**

- Daily low stock email report
- Weekly inventory summary
- Monthly stock valuation report

**D. System Maintenance**

- Audit log cleanup (keep last 12 months)
- Temp file cleanup
- Database optimization tasks

#### Implementation

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Every day at 9 AM
    $schedule->command('approvals:send-reminders')
             ->dailyAt('09:00');

    // Every Monday at 8 AM
    $schedule->command('inventory:low-stock-report')
             ->weeklyOn(1, '08:00');

    // First day of month
    $schedule->command('reports:monthly-summary')
             ->monthlyOn(1, '08:00');

    // Every night at 2 AM
    $schedule->command('system:cleanup')
             ->dailyAt('02:00');
}
```

---

## PHASE 4: Integration & Automation (Priority: 🟢 LOW-MEDIUM)

**Timeline:** Month 3-4  
**Total Effort:** 48-70 hours  
**Impact:** Strategic long-term value

### 4.1 API Integration - Accounting System 🔗

**Effort Estimate:** 20-30 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Features

- Export approved POs to accounting system
- Sync suppliers & Chart of Accounts
- Invoice matching (PO vs Invoice)
- Payment status tracking
- Reconciliation reports

---

### 4.2 Mobile PWA Application 📱

**Effort Estimate:** 40-60 hours  
**Business Value:** ⭐⭐⭐⭐⭐

#### Features

- PWA installable app
- Push notifications
- Offline mode for viewing
- Mobile-optimized approval flow
- Barcode scanning for GR/Put Away

---

## PHASE 5: Advanced Features (Priority: 🔵 OPTIONAL)

**Timeline:** Month 5+  
**Total Effort:** 66-85 hours  
**Impact:** Competitive advantage & future-proofing

### 5.1 Inventory Forecasting 📈

**Effort Estimate:** 16-20 hours

- Demand forecasting based on historical data
- Auto-suggest reorder points
- Safety stock calculations
- Seasonal adjustments

---

### 5.2 Contract Management 📑

**Effort Estimate:** 20-25 hours

- Supplier contracts database
- Price agreements tracking
- Contract renewal alerts
- Volume commitment tracking

---

### 5.3 Advanced Analytics with BI Tools 📊

**Effort Estimate:** 30-40 hours

- Power BI / Tableau integration
- Predictive analytics
- Custom dashboards per role
- Data warehouse export

---

## 📅 Implementation Strategy

### Approach A: Quick Wins First ⭐ **RECOMMENDED**

**Focus:** Features that are fast to implement with high immediate impact

**Sequence:**

1. Week 1: Email Notifications (4-6h)
2. Week 1-2: Approval Quick Actions (4-6h)
3. Week 2: Document Attachments (6-8h)
4. Week 3-4: Advanced Reporting Dashboard (12-16h)
5. Week 5: PR to PO Enhancement (6-8h)

**Benefits:**

- ✅ Users immediately feel improvements
- ✅ Positive momentum
- ✅ Quick ROI demonstration
- ✅ User adoption increases

---

### Approach B: Strategic Feature First

**Focus:** One big game-changing feature

**Sequence:**

1. Month 1: Advanced Reporting Dashboard (full implementation)
2. Month 2: Budget Management Module
3. Month 3: Vendor Portal

**Benefits:**

- ✅ Big impact showcase
- ✅ Management buy-in
- ✅ Competitive advantage
- ✅ Strategic value demonstration

---

### Approach C: Complete One Module

**Focus:** Deep dive into one module until perfect

**Sequence:**

1. Choose module: Procurement or Warehouse
2. Implement all missing features
3. Polish UX to production-grade
4. Use as reference for other modules

**Benefits:**

- ✅ One perfect module as showcase
- ✅ Learning foundation
- ✅ Best practices established
- ✅ Template for future modules

---

## 📊 Timeline & Resources

### Phase 1: Weeks 1-2 (14-20 hours)

| Feature                | Effort | Assignee       | Status  |
| ---------------------- | ------ | -------------- | ------- |
| Email Notifications    | 4-6h   | Backend Dev    | Pending |
| Approval Quick Actions | 4-6h   | Frontend Dev   | Pending |
| Document Attachments   | 6-8h   | Full-stack Dev | Pending |

### Phase 2: Weeks 3-6 (52-68 hours)

| Feature              | Effort | Assignee       | Status  |
| -------------------- | ------ | -------------- | ------- |
| Advanced Reporting   | 12-16h | Full-stack Dev | Pending |
| Budget Management    | 16-20h | Full-stack Dev | Pending |
| PR to PO Enhancement | 6-8h   | Full-stack Dev | Pending |
| Quality Inspection   | 10-12h | Full-stack Dev | Pending |
| Vendor Portal        | 20-30h | Full-stack Dev | Pending |

### Phase 3: Weeks 7-10 (18-24 hours)

| Feature         | Effort | Assignee     | Status  |
| --------------- | ------ | ------------ | ------- |
| Advanced Search | 8-10h  | Frontend Dev | Pending |
| Scheduled Jobs  | 8-10h  | Backend Dev  | Pending |

---

## 🎯 Success Metrics

### User Satisfaction Metrics

- ✅ Approval turnaround time reduced by 50%
- ✅ Document search time reduced by 70%
- ✅ User training time reduced by 40%
- ✅ System uptime > 99.5%

### Business Impact Metrics

- ✅ Procurement cycle time reduced by 30%
- ✅ Budget compliance improved to 95%
- ✅ Supplier response time improved by 40%
- ✅ Inventory accuracy > 98%

### Technical Metrics

- ✅ API response time < 200ms (95th percentile)
- ✅ Page load time < 2s
- ✅ Zero critical bugs in production
- ✅ Test coverage > 80%

---

## 📝 Decision Log

| Date       | Decision                       | Rationale                       | Owner         |
| ---------- | ------------------------------ | ------------------------------- | ------------- |
| 2026-01-04 | Created roadmap document       | Strategic planning for 2026     | Team Lead     |
| TBD        | Select implementation approach | To be decided with stakeholders | Product Owner |
| TBD        | Approve Phase 1 features       | Budget & resource allocation    | Management    |

---

## 🔗 Related Documentation

- [Current System Status](./NEXT_STEPS.md)
- [Approval Workflow Implementation](./APPROVAL_WORKFLOW_IMPLEMENTATION.md)
- [My Approvals Dashboard](./MY_APPROVALS_DASHBOARD.md)
- [Stock Reports Implementation](./STOCK_REPORTS_UI_IMPLEMENTATION.md)
- [Testing Guides](./TESTING_APPROVAL_WORKFLOW.md)

---

## 📞 Contact & Support

**Document Owner:** Development Team  
**Last Review:** January 4, 2026  
**Next Review:** February 1, 2026

For questions or suggestions about this roadmap, please contact the development team.

---

**Document Status:** 📋 **DRAFT - PENDING APPROVAL**

This roadmap should be reviewed and approved by stakeholders before implementation begins.
