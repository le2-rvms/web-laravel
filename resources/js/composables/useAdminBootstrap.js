import http from '@/lib/http'
import { reactive, readonly } from 'vue'

const state = reactive({
  loaded: false,
  loading: false,
  payload: null,
  stats: null,
})

export function useAdminBootstrap() {
  async function fetchBootstrap(force = false) {
    if (state.loading || (state.loaded && !force)) {
      return
    }

    state.loading = true

    try {
      const [payloadResponse, statsResponse] = await Promise.allSettled([
        http.get('/web-admin/app/bootstrap'),
        http.get('/api-admin/statistics'),
      ])

      if (payloadResponse.status === 'fulfilled') {
        state.payload = payloadResponse.value.data
        state.loaded = true
      }

      if (statsResponse.status === 'fulfilled') {
        state.stats = statsResponse.value.data?.data ?? statsResponse.value.data
      }
    } finally {
      state.loading = false
    }
  }

  return {
    state: readonly(state),
    fetchBootstrap,
  }
}
