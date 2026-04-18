<script setup>
import { computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { LayoutDashboard, LoaderCircle, LogOut } from 'lucide-vue-next'

import AppLogo from '@/components/AppLogo.vue'
import Button from '@/components/ui/button/Button.vue'
import { useAdminBootstrap } from '@/composables/useAdminBootstrap'

const { state, fetchBootstrap } = useAdminBootstrap()

const currentAdmin = computed(() => state.payload?.admin)

const navItems = computed(() =>
  state.payload?.nav ?? [
    {
      label: '仪表盘',
      href: '/web-admin',
      icon: 'layout-dashboard',
    },
  ],
)

function iconFor(name) {
  if (name === 'layout-dashboard') {
    return LayoutDashboard
  }

  return LayoutDashboard
}

function logout() {
  router.post('/web-admin/logout')
}

onMounted(() => {
  void fetchBootstrap()
})
</script>

<template>
  <Head title="管理后台" />

  <div class="min-h-screen px-4 py-4 md:px-6">
    <div class="mx-auto grid min-h-[calc(100vh-2rem)] max-w-7xl gap-4 md:grid-cols-[280px_1fr]">
      <aside class="rounded-[32px] border border-[var(--line)] bg-[var(--panel)] p-5 shadow-[0_20px_60px_rgba(28,25,23,0.08)] backdrop-blur">
        <AppLogo />

        <nav class="mt-10 space-y-2">
          <Link
            v-for="item in navItems"
            :key="item.href"
            :href="item.href"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-[var(--ink-soft)] transition hover:bg-black/5 hover:text-[var(--ink)]"
          >
            <component :is="iconFor(item.icon)" class="h-4 w-4" />
            <span>{{ item.label }}</span>
          </Link>
        </nav>

        <div class="mt-10 rounded-3xl bg-[var(--panel-muted)] p-4">
          <p class="text-xs uppercase tracking-[0.22em] text-[var(--ink-soft)]">
            当前角色
          </p>
          <div v-if="state.loading && !state.payload" class="mt-4 flex items-center gap-2 text-sm text-[var(--ink-soft)]">
            <LoaderCircle class="h-4 w-4 animate-spin" />
            <span>正在加载管理员信息...</span>
          </div>
          <div v-else class="mt-4 space-y-3">
            <p class="text-base font-semibold text-[var(--ink)]">
              {{ currentAdmin?.name ?? '未命名管理员' }}
            </p>
            <p class="text-sm text-[var(--ink-soft)]">
              {{ currentAdmin?.email }}
            </p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="role in state.payload?.roles ?? []"
                :key="role"
                class="rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-[var(--ink)]"
              >
                {{ role }}
              </span>
            </div>
          </div>
        </div>
      </aside>

      <div class="rounded-[32px] border border-[var(--line)] bg-[var(--panel-strong)] p-5 shadow-[0_20px_60px_rgba(28,25,23,0.06)]">
        <header class="flex flex-col gap-4 border-b border-[var(--line)] pb-5 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.22em] text-[var(--ink-soft)]">
              Admin Workspace
            </p>
            <h1 class="mt-2 text-3xl font-semibold text-[var(--ink)]">
              管理后台
            </h1>
          </div>

          <Button variant="outline" class="w-full md:w-auto" @click="logout">
            <LogOut class="h-4 w-4" />
            退出登录
          </Button>
        </header>

        <main class="pt-6">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
