<script setup>
import { cn } from '@/lib/utils'

defineProps({
  class: {
    type: String,
    default: '',
  },
  modelValue: {
    type: String,
    default: '',
  },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <input
    :class="
      cn(
        'flex h-11 w-full rounded-2xl border border-[var(--line)] bg-white/85 px-4 py-2 text-sm text-[var(--ink)] shadow-sm outline-none transition placeholder:text-stone-400 focus:border-[var(--brand)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--brand)_18%,white)]',
        $props.class,
      )
    "
    :value="modelValue"
    v-bind="$attrs"
    @input="$emit('update:modelValue', $event.target.value)"
  >
</template>
