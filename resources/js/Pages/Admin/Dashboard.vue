<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { KeyRound, ShieldCheck, UserRound } from 'lucide-vue-next'

import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardDescription from '@/components/ui/card/CardDescription.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useAdminBootstrap } from '@/composables/useAdminBootstrap'

defineOptions({
  layout: AdminLayout,
})

const { state, fetchBootstrap } = useAdminBootstrap()

const cards = computed(() => [
  {
    label: '当前管理员',
    value: state.payload?.admin.name ?? '加载中',
    icon: UserRound,
  },
  {
    label: '角色数量',
    value: String(state.payload?.roles.length ?? 0),
    icon: ShieldCheck,
  },
  {
    label: '权限数量',
    value: String(state.payload?.permissions.length ?? 0),
    icon: KeyRound,
  },
])

onMounted(() => {
  void fetchBootstrap()
})
</script>

<template>
  <Head title="仪表盘" />

  <div class="space-y-6">
    <section class="grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
      <Card>
        <CardHeader>
          <CardTitle>Admin Web 已接入</CardTitle>
          <CardDescription>
            当前后台页面运行在 Inertia + Vue 之上，登录改为 Fortify 会话模式，页面内继续复用现有
            <code>/api-admin/*</code>
            接口。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 md:grid-cols-3">
            <div
              v-for="card in cards"
              :key="card.label"
              class="rounded-3xl border border-[var(--line)] bg-white/85 p-5"
            >
              <component :is="card.icon" class="h-5 w-5 text-[var(--brand)]" />
              <p class="mt-4 text-sm text-[var(--ink-soft)]">
                {{ card.label }}
              </p>
              <p class="mt-2 text-2xl font-semibold text-[var(--ink)]">
                {{ card.value }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>接入状态</CardTitle>
          <CardDescription>
            首期只提供后台壳、认证页和基础启动数据。
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4 text-sm text-[var(--ink-soft)]">
          <div class="rounded-2xl bg-[var(--panel-muted)] px-4 py-3">
            Fortify 登录、登出、忘记密码、重置密码已纳入同一前缀空间。
          </div>
          <div class="rounded-2xl bg-[var(--panel-muted)] px-4 py-3">
            浏览器端不再写入 Bearer Token，统一依赖 Cookie + Session。
          </div>
          <div class="rounded-2xl bg-[var(--panel-muted)] px-4 py-3">
            现有 API Token 登录仍保留给 App、小程序和外部客户端。
          </div>
        </CardContent>
      </Card>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>权限快照</CardTitle>
          <CardDescription>
            前端只用这些权限做菜单裁剪，真正的访问控制仍由后端负责。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="permission in state.payload?.permissions ?? []"
              :key="permission"
              class="rounded-full border border-[var(--line)] bg-white px-3 py-1 text-xs text-[var(--ink)]"
            >
              {{ permission }}
            </span>
            <p v-if="!(state.payload?.permissions?.length)" class="text-sm text-[var(--ink-soft)]">
              当前未加载到权限列表。
            </p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>统计接口</CardTitle>
          <CardDescription>
            页面会尝试读取现有的轻量统计接口；如果环境尚未接入旧业务库，这里会保持为空。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <pre class="overflow-auto rounded-3xl bg-stone-950/90 p-4 text-xs text-stone-100">{{ JSON.stringify(state.stats, null, 2) || 'null' }}</pre>
        </CardContent>
      </Card>
    </section>
  </div>
</template>
