<template>
  <section
    class="rounded-[28px] border border-[var(--line)] bg-[var(--panel)] shadow-[0_20px_60px_rgba(28,25,23,0.08)] backdrop-blur"
    v-bind="$attrs"
  >
    <slot />
  </section>
</template>
