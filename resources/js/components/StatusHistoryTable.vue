<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type StatusHistoryRow = {
    id: number | string;
    created_at?: string | null;
    action?: string | null;
    from_status?: string | null;
    to_status?: string | null;
    actor_user_id?: number | string | null;
    actor?: {
        name?: string | null;
    } | null;

    // optional meta for note implementations (safe for PR)
    meta?: Record<string, any> | null;
};

const props = withDefaults(
    defineProps<{
        rows: StatusHistoryRow[];
        formatDateTime: (value?: string | null) => string;
        emptyText?: string;

        /**
         * If set, shows a Note column. Use getNote to customize the text.
         */
        showNote?: boolean;
        getNote?: (row: StatusHistoryRow) => string | null | undefined;
    }>(),
    {
        emptyText: 'No history.',
        showNote: false,
        getNote: (row: StatusHistoryRow) => (row as any)?.meta?.reason ?? null,
    },
);
</script>

<template>
    <div class="overflow-hidden rounded-lg border">
        <Table>
            <TableHeader class="bg-muted/40">
                <TableRow>
                    <TableHead>When</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>From</TableHead>
                    <TableHead>To</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead v-if="props.showNote">Note</TableHead>
                    <slot name="extraHead" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="h in props.rows" :key="h.id">
                    <TableCell>{{
                        props.formatDateTime(h.created_at)
                    }}</TableCell>
                    <TableCell>{{ h.action ?? '-' }}</TableCell>
                    <TableCell>{{ h.from_status ?? '-' }}</TableCell>
                    <TableCell>{{ h.to_status ?? '-' }}</TableCell>
                    <TableCell>
                        {{ h.actor?.name ?? h.actor_user_id ?? '-' }}
                    </TableCell>

                    <TableCell v-if="props.showNote">
                        <span v-if="props.getNote(h)">
                            {{ props.getNote(h) }}
                        </span>
                        <span v-else class="text-muted-foreground">-</span>
                    </TableCell>

                    <slot name="extraCells" :row="h" />
                </TableRow>

                <TableRow v-if="props.rows.length === 0">
                    <TableCell
                        :colspan="
                            5 +
                            (props.showNote ? 1 : 0) +
                            ($slots.extraHead ? 1 : 0)
                        "
                        class="py-6 text-center text-muted-foreground"
                    >
                        {{ props.emptyText }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
