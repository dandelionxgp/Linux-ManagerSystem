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
  }

  async function logout() {
    await logoutApi()
    token.value = ''
    user.value = null
    localStorage.removeItem('token')
  }

  async function fetchUser() {
    const res = await getCurrentUserApi()
    user.value = res.data
  }

  return { user, token, login, logout, fetchUser }
})
