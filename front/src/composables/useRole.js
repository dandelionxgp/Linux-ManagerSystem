import { useAuthStore } from '@/store/auth'
import { computed } from 'vue'

/**
 * 角色权限 composable
 *
 * 用法：
 *   const { isAdmin, isManager, isViewer, canWrite } = useRole()
 *   <el-button v-if="canWrite">新增</el-button>
 */
export function useRole() {
  const authStore = useAuthStore()

  const isAdmin   = computed(() => authStore.user?.role === 'admin')
  const isManager = computed(() => authStore.user?.role === 'manager')
  const isViewer  = computed(() => authStore.user?.role === 'viewer')

  /** 是否有写权限（非只读） */
  const canWrite  = computed(() => authStore.user?.role === 'admin' || authStore.user?.role === 'manager')

  return { isAdmin, isManager, isViewer, canWrite }
}
