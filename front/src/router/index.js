import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录' }
  },
  {
    path: '/',
    component: () => import('@/components/AppLayout.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '仪表盘' }
      },
      {
        path: 'products',
        name: 'ProductList',
        component: () => import('@/views/product/ProductList.vue'),
        meta: { title: '商品管理' }
      },
      {
        path: 'stock-in',
        name: 'StockInList',
        component: () => import('@/views/stock/StockInList.vue'),
        meta: { title: '入库管理' }
      },
      {
        path: 'stock-out',
        name: 'StockOutList',
        component: () => import('@/views/stock/StockOutList.vue'),
        meta: { title: '出库管理' }
      },
      {
        path: 'stock-query',
        name: 'StockQuery',
        component: () => import('@/views/inventory/StockQuery.vue'),
        meta: { title: '库存查询' }
      },
      {
        path: 'categories',
        name: 'CategoryManage',
        component: () => import('@/views/product/CategoryManage.vue'),
        meta: { title: '分类管理' }
      },
      {
        path: 'inventory',
        name: 'InventoryCheck',
        component: () => import('@/views/inventory/InventoryCheck.vue'),
        meta: { title: '库存盘点' }
      },
      {
        path: 'system/users',
        name: 'UserManage',
        component: () => import('@/views/system/UserManage.vue'),
        meta: { title: '用户管理', role: 'admin' }
      },
      {
        path: 'system/logs',
        name: 'OperationLog',
        component: () => import('@/views/system/OperationLog.vue'),
        meta: { title: '操作日志', role: 'admin' }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫：未登录跳转到登录页
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  if (to.path !== '/login' && !token) {
    next('/login')
  } else {
    next()
  }
})

export default router
