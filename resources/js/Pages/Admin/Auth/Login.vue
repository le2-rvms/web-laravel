<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { LoaderCircle } from 'lucide-vue-next'

import AppLogo from '@/components/AppLogo.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardDescription from '@/components/ui/card/CardDescription.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import Input from '@/components/ui/input/Input.vue'
import Label from '@/components/ui/label/Label.vue'

defineProps({
  status: {
    type: String,
    default: null,
  },
  canResetPassword: {
    type: Boolean,
    required: true,
  },
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/web-admin/login')
}
</script>

<template>
  <Head title="登录" />

  <div class="flex min-h-screen items-center justify-center px-4 py-8">
    <div class="grid w-full max-w-6xl gap-6 lg:grid-cols-[1.1fr_520px]">
      <section class="hidden rounded-[32px] border border-[var(--line)] bg-[var(--panel)] p-8 shadow-[0_24px_80px_rgba(28,25,23,0.09)] backdrop-blur lg:block">
        <AppLogo />
        <div class="mt-20 max-w-xl">
          <p class="text-sm uppercase tracking-[0.24em] text-[var(--ink-soft)]">
            Session-based Admin Console
          </p>
          <h1 class="mt-4 text-5xl font-semibold leading-tight text-[var(--ink)]">
            把后台登录切回浏览器原生最稳的方式。
          </h1>
          <p class="mt-6 text-lg leading-8 text-[var(--ink-soft)]">
            当前管理端 Web 使用 Fortify 会话登录，页面本身由 Inertia + Vue 承载，浏览器内继续复用既有
            <code>/api-admin/*</code>
            能力。
          </p>
        </div>
      </section>

      <Card class="overflow-hidden">
        <CardHeader>
          <AppLogo class="lg:hidden" />
          <CardTitle>登录管理后台</CardTitle>
          <CardDescription>
            使用管理员邮箱和密码登录，浏览器将保留安全会话，不再在本地持久化 API Token。
          </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
          <div
            v-if="status"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
          >
            {{ status }}
          </div>

          <form class="space-y-5" @submit.prevent="submit">
            <div>
              <Label for="email">邮箱</Label>
              <Input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="admin@example.com" />
              <p v-if="form.errors.email" class="mt-2 text-sm text-[var(--danger)]">
                {{ form.errors.email }}
              </p>
            </div>

            <div>
              <div class="mb-2 flex items-center justify-between">
                <Label for="password" class="mb-0">密码</Label>
                <Link
                  v-if="canResetPassword"
                  href="/web-admin/forgot-password"
                  class="text-sm font-medium text-[var(--brand)] hover:underline"
                >
                  忘记密码？
                </Link>
              </div>
              <Input id="password" v-model="form.password" type="password" autocomplete="current-password" placeholder="请输入密码" />
              <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--danger)]">
                {{ form.errors.password }}
              </p>
            </div>

            <label class="flex items-center gap-3 rounded-2xl bg-[var(--panel-muted)] px-4 py-3 text-sm text-[var(--ink-soft)]">
              <input
                v-model="form.remember"
                type="checkbox"
                class="h-4 w-4 rounded border-[var(--line)] text-[var(--brand)]"
              >
              记住当前浏览器会话
            </label>

            <Button type="submit" class="w-full" :disabled="form.processing">
              <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
              <span>{{ form.processing ? '登录中...' : '登录' }}</span>
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
