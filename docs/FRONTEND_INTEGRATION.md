# Frontend Integration Guide - Approval Workflow

## 📋 Overview

Backend sudah implement data-driven approval workflow. Frontend perlu menampilkan approval progress dan menyediakan tombol approve/reject.

---

## 🔌 New API Endpoints

### 1. Get Approval Progress

```typescript
GET /api/purchase-orders/{id}/approvals

// Response
{
  "data": [
    {
      "id": 1,
      "approval_workflow_id": 1,
      "approval_workflow_step_id": 1,
      "approvable_type": "App\\Models\\PurchaseOrder",
      "approvable_id": 1,
      "status": "APPROVED", // PENDING | APPROVED | REJECTED | SKIPPED | CANCELLED
      "assigned_to_user_id": null,
      "assigned_to_role": "finance",
      "approved_by_user_id": 5,
      "approved_at": "2025-12-28T10:30:00.000000Z",
      "rejected_by_user_id": null,
      "rejected_at": null,
      "rejection_reason": null,
      "comments": null,
      "meta": null,
      "created_at": "2025-12-28T09:00:00.000000Z",
      "updated_at": "2025-12-28T10:30:00.000000Z",
      "step": {
        "id": 1,
        "step_order": 1,
        "step_code": "FINANCE",
        "step_name": "Finance Review",
        "step_description": "Financial review and validation of Purchase Order",
        "approver_type": "ROLE",
        "approver_value": "finance",
        // ...
      },
      "assigned_to_user": null,
      "approved_by": {
        "id": 5,
        "name": "John Finance",
        "email": "finance@example.com"
      },
      "rejected_by": null
    },
    {
      "id": 2,
      "status": "PENDING",
      "step": {
        "step_name": "General Manager Approval",
        "step_code": "GM"
      },
      "assigned_to_role": "gm",
      // ...
    }
  ]
}
```

### 2. Approve Purchase Order

```typescript
POST /api/purchase-orders/{id}/approve

// No body needed (or optional comments)
{
  "comments": "Approved with notes..."
}

// Response: Updated PurchaseOrder object
{
  "data": { /* PurchaseOrder */ }
}
```

### 3. Reject Purchase Order (NEW)

```typescript
POST /api/purchase-orders/{id}/reject

// Body
{
  "reason": "Budget not available" // Required
}

// Response: Updated PurchaseOrder object with status REJECTED
{
  "data": { /* PurchaseOrder */ }
}
```

### 4. PO Show Endpoint (Updated)

```typescript
GET /api/purchase-orders/{id}

// Now includes 'approvals' in response
{
  "data": {
    "id": 1,
    "po_number": "PO-2025-001",
    "status": "IN_APPROVAL",
    // ... other fields
    "approvals": [ /* same as /approvals endpoint */ ]
  }
}
```

---

## 📦 TypeScript Types

```typescript
// Add to purchaseOrderApi.ts or create approvalApi.ts

export type ApprovalStatus =
    | 'PENDING'
    | 'APPROVED'
    | 'REJECTED'
    | 'SKIPPED'
    | 'CANCELLED';

export type ApprovalWorkflowStepDto = {
    id: number;
    step_order: number;
    step_code: string;
    step_name: string;
    step_description?: string | null;
    approver_type: 'ROLE' | 'USER' | 'DEPARTMENT_HEAD' | 'DYNAMIC';
    approver_value?: string | null;
    condition_field?: string | null;
    condition_operator?: string | null;
    condition_value?: string | null;
    is_required: boolean;
    allow_skip: boolean;
    allow_parallel: boolean;
    meta?: Record<string, any> | null;
};

export type ApprovalDto = {
    id: number;
    approval_workflow_id: number;
    approval_workflow_step_id: number;
    approvable_type: string;
    approvable_id: number;
    status: ApprovalStatus;
    assigned_to_user_id?: number | null;
    assigned_to_role?: string | null;
    approved_by_user_id?: number | null;
    approved_at?: string | null;
    rejected_by_user_id?: number | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    comments?: string | null;
    meta?: Record<string, any> | null;
    created_at: string;
    updated_at: string;

    // Relationships
    step?: ApprovalWorkflowStepDto;
    assigned_to_user?: { id: number; name: string; email: string } | null;
    approved_by?: { id: number; name: string; email: string } | null;
    rejected_by?: { id: number; name: string; email: string } | null;
};

// Update PurchaseOrderDto
export type PurchaseOrderDto = {
    // ... existing fields
    approvals?: ApprovalDto[];
};
```

---

## 🔧 API Client Functions

```typescript
// resources/js/services/purchaseOrderApi.ts

export async function getApprovals(poId: number) {
    return apiFetch<{ data: ApprovalDto[] }>(
        `/api/purchase-orders/${poId}/approvals`,
    );
}

export async function rejectPurchaseOrder(poId: number, reason: string) {
    return apiFetch<{ data: PurchaseOrderDto }>(
        `/api/purchase-orders/${poId}/reject`,
        {
            method: 'POST',
            body: JSON.stringify({ reason }),
        },
    );
}

// approvePurchaseOrder already exists, no changes needed
```

---

## 🎨 UI Components

### 1. ApprovalProgressWidget Component

```vue
<!-- components/ApprovalProgressWidget.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import type { ApprovalDto } from '@/services/purchaseOrderApi';
import StatusBadge from './StatusBadge.vue';

const props = defineProps<{
    approvals: ApprovalDto[];
}>();

const sortedApprovals = computed(() =>
    [...props.approvals].sort(
        (a, b) => (a.step?.step_order ?? 0) - (b.step?.step_order ?? 0),
    ),
);

function getStatusColor(status: string): string {
    switch (status) {
        case 'APPROVED':
            return 'success';
        case 'REJECTED':
            return 'destructive';
        case 'PENDING':
            return 'warning';
        case 'CANCELLED':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getStatusIcon(status: string): string {
    switch (status) {
        case 'APPROVED':
            return '✅';
        case 'REJECTED':
            return '❌';
        case 'PENDING':
            return '⏳';
        case 'CANCELLED':
            return '🚫';
        default:
            return '○';
    }
}
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-lg font-semibold">Approval Progress</h3>

        <div
            v-if="approvals.length === 0"
            class="text-sm text-muted-foreground"
        >
            No approval workflow initiated yet.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="approval in sortedApprovals"
                :key="approval.id"
                class="flex items-start gap-4 rounded-lg border p-4"
            >
                <!-- Step Number -->
                <div
                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold"
                >
                    {{ approval.step?.step_order }}
                </div>

                <!-- Step Info -->
                <div class="flex-1 space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">
                                {{ approval.step?.step_name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ approval.step?.step_description }}
                            </p>
                        </div>
                        <StatusBadge :status="approval.status" />
                    </div>

                    <!-- Approver Info -->
                    <div class="text-sm text-muted-foreground">
                        <span v-if="approval.assigned_to_role">
                            Assigned to:
                            <span class="font-medium">{{
                                approval.assigned_to_role
                            }}</span>
                        </span>
                        <span v-else-if="approval.assigned_to_user">
                            Assigned to:
                            <span class="font-medium">{{
                                approval.assigned_to_user.name
                            }}</span>
                        </span>
                    </div>

                    <!-- Approval Details -->
                    <div v-if="approval.status === 'APPROVED'" class="text-sm">
                        <p class="text-green-600">
                            ✅ Approved by
                            <span class="font-medium">{{
                                approval.approved_by?.name
                            }}</span>
                            on {{ formatDateTime(approval.approved_at) }}
                        </p>
                        <p
                            v-if="approval.comments"
                            class="mt-1 text-muted-foreground italic"
                        >
                            "{{ approval.comments }}"
                        </p>
                    </div>

                    <div v-if="approval.status === 'REJECTED'" class="text-sm">
                        <p class="text-red-600">
                            ❌ Rejected by
                            <span class="font-medium">{{
                                approval.rejected_by?.name
                            }}</span>
                            on {{ formatDateTime(approval.rejected_at) }}
                        </p>
                        <p
                            v-if="approval.rejection_reason"
                            class="mt-1 text-red-600"
                        >
                            Reason:
                            <span class="italic">{{
                                approval.rejection_reason
                            }}</span>
                        </p>
                    </div>

                    <div
                        v-if="approval.status === 'PENDING'"
                        class="text-sm text-yellow-600"
                    >
                        ⏳ Waiting for approval
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

### 2. Rejection Modal Component

```vue
<!-- components/RejectPurchaseOrderModal.vue -->
<script setup lang="ts">
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    open: boolean;
    poNumber: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [reason: string];
}>();

const reason = ref('');
const isSubmitting = ref(false);

function handleConfirm() {
    if (!reason.value.trim()) {
        return;
    }
    emit('confirm', reason.value);
}

function handleClose() {
    if (!isSubmitting.value) {
        reason.value = '';
        emit('update:open', false);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject Purchase Order</DialogTitle>
                <DialogDescription>
                    You are about to reject PO {{ poNumber }}. This action will
                    cancel all remaining approvals.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium"
                        >Rejection Reason *</label
                    >
                    <Textarea
                        v-model="reason"
                        placeholder="Please provide a reason for rejection..."
                        rows="4"
                        class="mt-2"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="handleClose"
                    :disabled="isSubmitting"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    @click="handleConfirm"
                    :disabled="!reason.trim() || isSubmitting"
                >
                    {{ isSubmitting ? 'Rejecting...' : 'Reject PO' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

---

## 🔨 Integration in PO Show Page

```vue
<!-- pages/purchase-orders/Show.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import ApprovalProgressWidget from '@/components/ApprovalProgressWidget.vue';
import RejectPurchaseOrderModal from '@/components/RejectPurchaseOrderModal.vue';
import {
    getPurchaseOrder,
    approvePurchaseOrder,
    rejectPurchaseOrder,
    type PurchaseOrderDto,
} from '@/services/purchaseOrderApi';

const props = defineProps<{ purchaseOrderId: number }>();

const po = ref<PurchaseOrderDto | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const rejectModalOpen = ref(false);

// Check if current user can approve
const canApprove = computed(() => {
    if (!po.value) return false;

    // Check if PO is in approvable state
    if (!['SUBMITTED', 'IN_APPROVAL'].includes(po.value.status)) {
        return false;
    }

    // Check if there's a pending approval for current user's role
    const nextPending = po.value.approvals?.find((a) => a.status === 'PENDING');
    if (!nextPending) return false;

    // You need to check current user's role against nextPending.assigned_to_role
    // Implement based on your auth system
    return true; // Placeholder
});

async function handleApprove() {
    if (!po.value) return;

    try {
        const res = await approvePurchaseOrder(po.value.id);
        po.value = res.data;
    } catch (e: any) {
        error.value = e?.payload?.message ?? 'Failed to approve';
    }
}

async function handleReject(reason: string) {
    if (!po.value) return;

    try {
        const res = await rejectPurchaseOrder(po.value.id, reason);
        po.value = res.data;
        rejectModalOpen.value = false;
    } catch (e: any) {
        error.value = e?.payload?.message ?? 'Failed to reject';
    }
}

// ... existing code
</script>

<template>
    <AppLayout>
        <!-- ... existing header -->

        <!-- Approval Section -->
        <div v-if="po?.approvals && po.approvals.length > 0" class="mt-8">
            <ApprovalProgressWidget :approvals="po.approvals" />

            <!-- Approve/Reject Buttons -->
            <div v-if="canApprove" class="mt-4 flex gap-3">
                <Button @click="handleApprove"> ✅ Approve </Button>
                <Button variant="destructive" @click="rejectModalOpen = true">
                    ❌ Reject
                </Button>
            </div>
        </div>

        <!-- ... existing PO details -->

        <!-- Rejection Modal -->
        <RejectPurchaseOrderModal
            v-model:open="rejectModalOpen"
            :po-number="po?.po_number ?? ''"
            @confirm="handleReject"
        />
    </AppLayout>
</template>
```

---

## 🎯 Implementation Checklist

### Phase 1: Basic Display

- [ ] Add TypeScript types for Approval & ApprovalWorkflowStep
- [ ] Update PurchaseOrderDto to include approvals
- [ ] Update API client with new functions
- [ ] Create ApprovalProgressWidget component
- [ ] Integrate widget in PO Show page

### Phase 2: Approve/Reject Actions

- [ ] Create RejectPurchaseOrderModal component
- [ ] Add approve/reject buttons with proper authorization
- [ ] Handle API responses & errors
- [ ] Show success/error toasts

### Phase 3: Real-time Updates (Optional)

- [ ] Add polling or WebSocket for approval status updates
- [ ] Show notifications when approval status changes
- [ ] Add pending approvals counter in navigation

---

## 🔍 Testing

### Manual Testing

1. Login as Finance user
2. Navigate to PO in SUBMITTED status
3. Should see "Finance Review" as PENDING
4. Click Approve → Should update to APPROVED
5. If more steps, should show next PENDING
6. Try Reject → Should cancel all remaining

### Edge Cases

- PO with no approvals (not submitted yet)
- PO already fully approved
- PO rejected
- User without permission to approve

---

## 📝 Notes

1. **Authorization**: Backend already enforces who can approve via Policy. Frontend just needs to hide/show buttons based on user role.

2. **Status Mapping**:
    - `SUBMITTED` → First approval pending
    - `IN_APPROVAL` → Some approved, more pending
    - `APPROVED` → All approved
    - `REJECTED` → Rejected by someone

3. **Comments**: Currently optional for approve. Consider making it required if business needs audit trail.

4. **Notifications**: Consider adding email/in-app notifications when approval assigned.

---

Need any clarification or want me to generate specific components? 🚀
