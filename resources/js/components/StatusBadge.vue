<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { getStatusUi, type StatusModule } from '@/lib/statusUi';
import { computed } from 'vue';

type Props = {
    status: string | null | undefined;
    module?: StatusModule;
    variant?: 'default' | 'secondary' | 'destructive' | 'outline';
};

const props = withDefaults(defineProps<Props>(), {
    module: undefined,
    variant: 'secondary',
});

const ui = computed(() =>
    getStatusUi({ module: props.module, status: props.status }),
);
</script>

<template>
    <Badge :variant="variant" :class="ui.badgeClass">
        <slot>{{ status }}</slot>
    </Badge>
</template>
