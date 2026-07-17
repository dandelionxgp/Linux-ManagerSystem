import { defineStore } from 'pinia'
import { ref } from 'vue'
import { loginApi, logoutApi, getCurrentUserApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || '')

  async function login(username, password) {
    const res = await loginApi({ username, password })
    token.value = res.data.token
    localStorage.setItem('token', token.value)
    await fetchUser()
    // 缓存角色供路由守卫使用
    if (user.value?.role) {
      localStorage.setItem('userRole', user.value.role)
    }
  }

  async function logout() {
    try {
      await logoutApi()
    } catch {
      // token 可能已失效，忽略 API 错误
    }
    token.value = ''
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('userRole')
  }

  async function fetchUser() {
    const res = await getCurrentUserApi()
    user.value = res.data
    // 同步角色到 localStorage
    if (res.data?.role) {
      localStorage.setItem('userRole', res.data.role)
    }
  }

  return { user, token, login, logout, fetchUser }
})
