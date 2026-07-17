<template>
  <el-container style="height: 100vh">
    <!-- 侧边栏 -->
    <el-aside width="220px" style="background: #304156">
      <div class="logo">库存管理系统</div>
      <el-menu :default-active="route.path" router background-color="#304156"
        text-color="#bfcbd9" active-text-color="#409EFF">
        <el-menu-item index="/dashboard">
          <el-icon><HomeFilled /></el-icon><span>仪表盘</span>
        </el-menu-item>
        <el-sub-menu index="product">
          <template #title><el-icon><Goods /></el-icon><span>商品管理</span></template>
          <el-menu-item index="/products">商品列表</el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="stock" v-if="authStore.user?.role !== 'viewer'">
          <template #title><el-icon><List /></el-icon><span>出入库管理</span></template>
          <el-menu-item index="/stock-in">入库管理</el-menu-item>
          <el-menu-item index="/stock-out">出库管理</el-menu-item>
        </el-sub-menu>
        <el-menu-item index="/stock-query">
          <el-icon><Search /></el-icon><span>库存查询</span>
        </el-menu-item>
        <el-menu-item index="/inventory" v-if="authStore.user?.role !== 'viewer'">
          <el-icon><Check /></el-icon><span>库存盘点</span>
        </el-menu-item>
        <el-sub-menu index="base">
          <template #title><el-icon><FolderOpened /></el-icon><span>基础数据</span></template>
          <el-menu-item index="/categories">分类管理</el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="system" v-if="authStore.user?.role === 'admin'">
          <template #title><el-icon><Setting /></el-icon><span>系统管理</span></template>
          <el-menu-item index="/system/users">用户管理</el-menu-item>
          <el-menu-item index="/system/logs">操作日志</el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>

    <!-- 右侧区域 -->
    <el-container>
      <el-header style="display: flex; justify-content: space-between; align-items: center">
        <div>{{ route.meta.title }}</div>
        <div>
          <span style="margin-right: 10px">{{ authStore.user?.real_name }}</span>
          <el-button text @click="handleLogout">退出</el-button>
        </div>
      </el-header>
      <el-main>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import {
  HomeFilled, Goods, List, Search, Check, FolderOpened, Setting
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

// 页面刷新时恢复用户信息（含角色缓存）
onMounted(async () => {
  if (authStore.token && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch {
      // token 已过期 → 跳转登录
      authStore.logout()
      router.push('/login')
    }
  }
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  letter-spacing: 2px;
}
.el-header {
  background: #fff;
  border-bottom: 1px solid #e6e6e6;
}
.el-main {
  background: #f0f2f5;
}
</style>
