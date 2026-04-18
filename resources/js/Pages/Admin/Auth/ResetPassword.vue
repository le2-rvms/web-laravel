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

const props = defineProps({
  token: {
    type: String,
    required: true,
  },
  email: {
    type: String,
    required: true,
  },
  status: {
    type: String,
    default: null,
  },
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/web-admin/reset-password', {
    onFinish: () => {
      form.reset('password', 'password_confirmation')
    },
  })
}
</script>

<template>
  <Head title="设置新密码" />

  <div class="flex min-h-screen items-center justify-center px-4 py-8">
    <Card class="w-full max-w-xl">
      <CardHeader>
        <AppLogo />
        <CardTitle>设置新的登录密码</CardTitle>
        <CardDescription>
          输入一个新的管理员密码，完成后会跳转回登录页。
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
            <Input id="email" v-model="form.email" type="email" autocomplete="email" readonly />
            <p v-if="form.errors.email" class="mt-2 text-sm text-[var(--danger)]">
              {{ form.errors.email }}
            </p>
          </div>

          <div>
            <Label for="password">新密码</Label>
            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" />
            <p v-if="form.errors.password" class="mt-2 text-sm text-[var(--danger)]">
              {{ form.errors.password }}
            </p>
          </div>

          <div>
            <Label for="password_confirmation">确认密码</Label>
            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <Button type="submit" class="flex-1" :disabled="form.processing">
              <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
              <span>{{ form.processing ? '提交中...' : '重置密码' }}</span>
            </Button>
            <Link
              href="/web-admin/login"
              class="inline-flex flex-1 items-center justify-center rounded-2xl border border-[var(--line)] bg-white/70 px-4 py-2.5 text-sm font-medium text-[var(--ink)] transition hover:bg-white"
            >
              返回登录
            </Link>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
