<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    createApprovalWorkflow,
    createApprovalWorkflowStep,
    deleteApprovalWorkflow,
    deleteApprovalWorkflowStep,
    updateApprovalWorkflow,
    updateApprovalWorkflowStep,
    type ApprovalWorkflowDto,
    type ApprovalWorkflowStepDto,
} from '@/services/approvalWorkflowApi';
import { getRoles, type RoleDto } from '@/services/userAdminApi';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ workflow: ApprovalWorkflowDto | null }>();

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const roles = ref<RoleDto[]>([]);

const form = reactive({
    code: '',
    name: '',
    description: '',
    document_type: 'PURCHASE_ORDER' as string,
    is_active: true,
});

const steps = ref<ApprovalWorkflowStepDto[]>([]);
const stepForm = reactive({
    editing: false,
    editingId: null as number | null,
    step_order: 1,
    approver_type: 'ROLE' as 'ROLE' | 'USER' | 'DEPARTMENT_HEAD' | 'DYNAMIC',
    approver_role: '',
    approver_user_id: null as number | null,
    condition_field: '',
    condition_operator: '',
    condition_value: '',
    is_final_step: false,
});

const isEdit = computed(() => props.workflow !== null);

function setFromDto(dto: ApprovalWorkflowDto) {
    form.code = dto.code;
    form.name = dto.name;
    form.description = dto.description ?? '';
    form.document_type = dto.document_type;
    form.is_active = dto.is_active;
    steps.value = dto.steps ?? [];
}

async function load() {
    loading.value = true;
    error.value = null;

    try {
        const rolesRes = await getRoles();
        roles.value = rolesRes.data;

        if (isEdit.value && props.workflow) {
            // If editing, workflow is already loaded via Inertia props
            setFromDto(props.workflow);
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to load form';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = null;

    try {
        if (isEdit.value && props.workflow) {
            await updateApprovalWorkflow(props.workflow.id, {
                code: form.code,
                name: form.name,
                description: form.description || null,
                document_type: form.document_type,
                is_active: form.is_active,
            });
        } else {
            await createApprovalWorkflow({
                code: form.code,
                name: form.name,
                description: form.description || null,
                document_type: form.document_type,
                is_active: form.is_active,
            });
        }

        router.visit('/approval-workflows');
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to save workflow';
    } finally {
        saving.value = false;
    }
}

function resetStepForm() {
    stepForm.editing = false;
    stepForm.editingId = null;
    stepForm.step_order = steps.value.length + 1;
    stepForm.approver_type = 'ROLE';
    stepForm.approver_role = '';
    stepForm.approver_user_id = null;
    stepForm.condition_field = '';
    stepForm.condition_operator = '';
    stepForm.condition_value = '';
    stepForm.is_final_step = false;
}

function editStep(step: ApprovalWorkflowStepDto) {
    stepForm.editing = true;
    stepForm.editingId = step.id;
    stepForm.step_order = step.step_order;
    stepForm.approver_type = step.approver_type;
    stepForm.approver_role = step.approver_role ?? '';
    stepForm.approver_user_id = step.approver_user_id ?? null;
    stepForm.condition_field = step.condition_field ?? '';
    stepForm.condition_operator = step.condition_operator ?? '';
    stepForm.condition_value = step.condition_value ?? '';
    stepForm.is_final_step = step.is_final_step ?? false;
}

async function saveStep() {
    if (!isEdit.value || !props.workflow) {
        error.value = 'Please save workflow first before adding steps';
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        const payload = {
            step_order: stepForm.step_order,
            approver_type: stepForm.approver_type,
            approver_role:
                stepForm.approver_type === 'ROLE'
                    ? stepForm.approver_role
                    : null,
            approver_user_id:
                stepForm.approver_type === 'USER'
                    ? stepForm.approver_user_id
                    : null,
            condition_field: stepForm.condition_field || null,
            condition_operator: stepForm.condition_operator || null,
            condition_value: stepForm.condition_value || null,
            is_final_step: stepForm.is_final_step,
        };

        if (stepForm.editing && stepForm.editingId) {
            const res = await updateApprovalWorkflowStep(
                props.workflow.id,
                stepForm.editingId,
                payload,
            );
            const idx = steps.value.findIndex(
                (s) => s.id === stepForm.editingId,
            );
            if (idx >= 0) steps.value[idx] = res.data;
        } else {
            const res = await createApprovalWorkflowStep(
                props.workflow.id,
                payload,
            );
            steps.value.push(res.data);
        }

        resetStepForm();
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to save step';
    } finally {
        saving.value = false;
    }
}

async function removeStep(stepId: number) {
    if (!isEdit.value || !props.workflow) return;
    if (!confirm('Are you sure you want to delete this step?')) return;

    saving.value = true;
    error.value = null;

    try {
        await deleteApprovalWorkflowStep(props.workflow.id, stepId);
        steps.value = steps.value.filter((s) => s.id !== stepId);
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to delete step';
    } finally {
        saving.value = false;
    }
}

async function deleteWorkflow() {
    if (!isEdit.value || !props.workflow) return;
    if (
        !confirm(
            'Are you sure you want to delete this workflow? This cannot be undone.',
        )
    )
        return;

    saving.value = true;
    error.value = null;

    try {
        await deleteApprovalWorkflow(props.workflow.id);
        router.visit('/approval-workflows');
    } catch (e: any) {
        error.value =
            e?.payload?.message ?? e?.message ?? 'Failed to delete workflow';
    } finally {
        saving.value = false;
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Approval Workflows',
        href: '/approval-workflows',
    },
    {
        title: isEdit.value ? 'Edit' : 'Create',
        href: '#',
    },
];

onMounted(load);
</script>

<template>
    <Head
        :title="isEdit ? 'Edit Approval Workflow' : 'Create Approval Workflow'"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">
                    {{
                        isEdit
                            ? 'Edit Approval Workflow'
                            : 'Create Approval Workflow'
                    }}
                </h1>
                <div class="flex gap-2">
                    <Button
                        v-if="isEdit"
                        variant="destructive"
                        @click="deleteWorkflow"
                        :disabled="saving"
                    >
                        Delete Workflow
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/approval-workflows">Back</Link>
                    </Button>
                </div>
            </div>

            <div
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="mt-6 text-sm text-muted-foreground">
                Loading…
            </div>

            <div v-else class="mt-6 space-y-6">
                <!-- Workflow Basic Info -->
                <Card class="p-6">
                    <h2 class="mb-4 text-lg font-semibold">Workflow Details</h2>
                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <label class="text-sm font-medium">Code</label>
                            <Input
                                v-model="form.code"
                                required
                                placeholder="e.g. PO_STANDARD"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium">Name</label>
                            <Input
                                v-model="form.name"
                                required
                                placeholder="e.g. Standard Purchase Order Approval"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium"
                                >Description</label
                            >
                            <textarea
                                v-model="form.description"
                                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Optional description"
                            />
                        </div>

                        <div>
                            <label class="text-sm font-medium"
                                >Document Type</label
                            >
                            <select
                                v-model="form.document_type"
                                class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                required
                            >
                                <option value="PURCHASE_REQUEST">
                                    Purchase Request
                                </option>
                                <option value="PURCHASE_ORDER">
                                    Purchase Order
                                </option>
                                <option value="GOODS_RECEIPT">
                                    Goods Receipt
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="is_active"
                                v-model="form.is_active"
                                class="h-4 w-4"
                            />
                            <label for="is_active" class="text-sm font-medium"
                                >Active</label
                            >
                        </div>

                        <Button type="submit" :disabled="saving">
                            {{
                                saving
                                    ? 'Saving...'
                                    : isEdit
                                      ? 'Update Workflow'
                                      : 'Create Workflow'
                            }}
                        </Button>
                    </form>
                </Card>

                <!-- Approval Steps -->
                <Card v-if="isEdit" class="p-6">
                    <h2 class="mb-4 text-lg font-semibold">Approval Steps</h2>

                    <div class="mb-6 space-y-3">
                        <div
                            v-for="(step, idx) in steps"
                            :key="step.id"
                            class="flex items-center gap-3 rounded-lg border p-4"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <Badge>Step {{ step.step_order }}</Badge>
                                    <Badge variant="outline">{{
                                        step.approver_type
                                    }}</Badge>
                                    <Badge
                                        v-if="step.is_final_step"
                                        variant="secondary"
                                        >Final</Badge
                                    >
                                    <Badge
                                        v-if="step.is_required"
                                        variant="default"
                                        >Required</Badge
                                    >
                                </div>

                                <p
                                    v-if="step.step_name"
                                    class="mt-2 text-sm font-medium"
                                >
                                    {{ step.step_name }}
                                </p>

                                <p
                                    v-if="step.step_description"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ step.step_description }}
                                </p>

                                <p class="mt-2 text-sm">
                                    <span class="text-muted-foreground"
                                        >Approver:
                                    </span>
                                    <span v-if="step.approver_type === 'ROLE'">
                                        Role:
                                        <strong>{{
                                            step.approver_role ||
                                            step.approver_value
                                        }}</strong>
                                    </span>
                                    <span
                                        v-else-if="
                                            step.approver_type === 'USER'
                                        "
                                    >
                                        User ID:
                                        <strong>{{
                                            step.approver_user_id ||
                                            step.approver_value
                                        }}</strong>
                                    </span>
                                    <span
                                        v-else-if="
                                            step.approver_type ===
                                            'DEPARTMENT_HEAD'
                                        "
                                    >
                                        <strong>Department Head</strong>
                                    </span>
                                    <span v-else>
                                        <strong>Dynamic</strong> ({{
                                            step.approver_value
                                        }})
                                    </span>
                                </p>
                                <p
                                    v-if="step.condition_field"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    <span class="text-muted-foreground"
                                        >Condition:
                                    </span>
                                    {{ step.condition_field }}
                                    {{ step.condition_operator }}
                                    {{ step.condition_value }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    @click="editStep(step)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    @click="removeStep(step.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="steps.length === 0"
                            class="rounded-lg border border-dashed p-6 text-center text-muted-foreground"
                        >
                            No steps yet. Add your first approval step below.
                        </div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <h3 class="mb-4 font-medium">
                            {{
                                stepForm.editing ? 'Edit Step' : 'Add New Step'
                            }}
                        </h3>
                        <form class="space-y-4" @submit.prevent="saveStep">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium"
                                        >Step Order</label
                                    >
                                    <Input
                                        v-model.number="stepForm.step_order"
                                        type="number"
                                        required
                                        min="1"
                                    />
                                </div>

                                <div>
                                    <label class="text-sm font-medium"
                                        >Approver Type</label
                                    >
                                    <select
                                        v-model="stepForm.approver_type"
                                        class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        required
                                    >
                                        <option value="ROLE">Role</option>
                                        <option value="USER">User</option>
                                        <option value="DEPARTMENT_HEAD">
                                            Department Head
                                        </option>
                                        <option value="DYNAMIC">Dynamic</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="stepForm.approver_type === 'ROLE'">
                                <label class="text-sm font-medium">Role</label>
                                <select
                                    v-model="stepForm.approver_role"
                                    class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                    required
                                >
                                    <option value="">Select role...</option>
                                    <option
                                        v-for="role in roles"
                                        :key="role.name"
                                        :value="role.name"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="stepForm.approver_type === 'USER'">
                                <label class="text-sm font-medium"
                                    >User ID</label
                                >
                                <Input
                                    :model-value="
                                        stepForm.approver_user_id ?? ''
                                    "
                                    @update:model-value="
                                        stepForm.approver_user_id = $event
                                            ? Number($event)
                                            : null
                                    "
                                    type="number"
                                    :required="
                                        stepForm.approver_type === 'USER'
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <h4 class="text-sm font-medium">
                                    Condition (Optional)
                                </h4>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="text-sm">Field</label>
                                        <Input
                                            v-model="stepForm.condition_field"
                                            placeholder="e.g. total_amount"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-sm">Operator</label>
                                        <select
                                            v-model="
                                                stepForm.condition_operator
                                            "
                                            class="mt-1 w-full rounded-md border bg-background px-3 py-2"
                                        >
                                            <option value="">-</option>
                                            <option value=">=">&gt;=</option>
                                            <option value=">">&gt;</option>
                                            <option value="<=">&lt;=</option>
                                            <option value="<">&lt;</option>
                                            <option value="=">=</option>
                                            <option value="!=">!=</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-sm">Value</label>
                                        <Input
                                            v-model="stepForm.condition_value"
                                            placeholder="e.g. 50000000"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_final_step"
                                    v-model="stepForm.is_final_step"
                                    class="h-4 w-4"
                                />
                                <label
                                    for="is_final_step"
                                    class="text-sm font-medium"
                                    >Final Step</label
                                >
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="saving">
                                    {{
                                        saving
                                            ? 'Saving...'
                                            : stepForm.editing
                                              ? 'Update Step'
                                              : 'Add Step'
                                    }}
                                </Button>
                                <Button
                                    v-if="stepForm.editing"
                                    type="button"
                                    variant="outline"
                                    @click="resetStepForm"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>

                <div
                    v-if="!isEdit"
                    class="rounded-lg border border-dashed bg-muted/20 p-6 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        Save the workflow first before adding approval steps.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
