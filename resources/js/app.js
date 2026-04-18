import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { InertiaProgress } from '@inertiajs/progress'
import { createApp, h } from 'vue'

const appName = document.title || 'Laravel'
const pages = import.meta.glob('./Pages/**/*.vue')

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) => {
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      throw new Error(`Unknown Inertia page: ${name}`)
    }

    return page().then((module) => module.default)
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})

InertiaProgress.init({
  color: '#0f766e',
  showSpinner: false,
})
