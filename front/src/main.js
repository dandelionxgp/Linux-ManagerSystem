import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'

import App from './App.vue'
import router from './router'

async function initApp() {
  // 启动时验证已存储的 token 是否有效
  const token = localStorage.getItem('token')
  if (token) {
    try {
      const resp = await fetch('/api/auth/me', {
        headers: { Authorization: `Bearer ${token}` }
      })
      if (resp.ok) {
        const json = await resp.json()
        if (json.code === 0 && json.data?.role) {
          localStorage.setItem('userRole', json.data.role)
        }
      } else {
        // token 无效 → 清除
        localStorage.removeItem('token')
        localStorage.removeItem('userRole')
      }
    } catch {
      // 网络错误或其他 → 保留 token，交给后续 axios 拦截器处理
    }
  }

  const app = createApp(App)
  app.use(createPinia())
  app.use(router)
  app.use(ElementPlus, { locale: zhCn })
  app.mount('#app')
}

initApp()
