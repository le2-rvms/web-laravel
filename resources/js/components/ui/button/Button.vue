<script setup>
import { cva } from 'class-variance-authority'
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 rounded-2xl text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-60',
  {
    variants: {
      variant: {
        default: 'bg-[var(--brand)] px-4 py-2.5 text-[var(--brand-ink)] shadow-sm hover:opacity-95',
        ghost: 'px-3 py-2 text-[var(--ink-soft)] hover:bg-black/5 hover:text-[var(--ink)]',
        outline: 'border border-[var(--line)] bg-white/70 px-4 py-2.5 text-[var(--ink)] hover:bg-white',
      },
      size: {
        default: 'h-11',
        sm: 'h-9 rounded-xl px-3 text-xs',
        lg: 'h-12 px-5 text-sm',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

const props = defineProps({
  type: {
    type: String,
    default: 'button',
  },
  class: {
    type: String,
    default: '',
  },
  variant: {
    type: String,
    default: 'default',
  },
  size: {
    type: String,
    default: 'default',
  },
})

const classes = computed(() => cn(buttonVariants({ variant: props.variant, size: props.size }), props.class))
</script>

<template>
  <button :type="type" :class="classes">
    <slot />
  </button>
</template>
