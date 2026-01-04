<script setup lang="ts">
import { computed } from 'vue'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'

interface Props {
  checked?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  checked: false,
  disabled: false
})

const emit = defineEmits<{
  'update:checked': [value: boolean]
}>()

const isChecked = computed({
  get: () => props.checked,
  set: (value) => emit('update:checked', value)
})
</script>

<template>
  <Checkbox
    :checked="isChecked"
    @update:checked="isChecked = $event"
    :disabled="disabled"
  />
</template>
