<template>
  <div>
    <!-- 筛选工具栏 -->
    <div class="toolbar">
      <el-select v-model="filterModule" placeholder="按模块筛选" clearable style="width: 130px" @change="fetchList">
        <el-option v-for="m in filterOptions.modules" :key="m" :label="moduleMap[m] || m" :value="m" />
      </el-select>
      <el-select v-model="filterAction" placeholder="按操作类型" clearable style="width: 120px; margin-left: 10px" @change="fetchList">
        <el-option v-for="a in filterOptions.actions" :key="a" :label="actionMap[a] || a" :value="a" />
      </el-select>
      <el-input v-model="keyword" placeholder="搜索描述/操作人" clearable style="width: 200px; margin-left: 10px"
        @clear="fetchList" @keyup.enter="fetchList" />
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至"
        start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD"
        style="margin-left: 10px; width: 260px" @change="fetchList" />
      <el-button style="margin-left: auto" @click="fetchList">查询</el-button>
    </div>

    <!-- 日志表格 -->
    <el-table :data="list" stripe border style="margin-top: 15px" v-loading="loading">
      <el-table-column prop="id" label="ID" width="65" />
      <el-table-column label="操作人" width="110">
        <template #default="{ row }">
          {{ row.user?.real_name || row.user?.username || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作类型" width="85">
        <template #default="{ row }">
          <el-tag v-if="row.action === 'create'" type="success" size="small">创建</el-tag>
          <el-tag v-else-if="row.action === 'update'" type="warning" size="small">更新</el-tag>
          <el-tag v-else-if="row.action === 'delete'" type="danger" size="small">删除</el-tag>
          <el-tag v-else-if="row.action === 'reverse'" type="info" size="small">冲销</el-tag>
          <el-tag v-else-if="row.action === 'confirm'" type="primary" size="small">确认</el-tag>
          <el-tag v-else size="small">{{ row.action }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="模块" width="90">
        <template #default="{ row }">
          {{ moduleMap[row.module] || row.module }}
        </template>
      </el-table-column>
      <el-table-column prop="description" label="操作描述" min-width="220" show-overflow-tooltip />
      <el-table-column prop="ip_address" label="IP 地址" width="140" />
      <el-table-column prop="created_at" label="操作时间" width="170" sortable />
    </el-table>

    <!-- 分页 -->
    <div class="pagination-wrap">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize"
        :page-sizes="[15, 20, 50, 100]" :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList" @current-change="fetchList" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getLogListApi, getLogOptionsApi } from '@/api/log'

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)

const keyword = ref('')
const filterModule = ref(null)
const filterAction = ref(null)
const dateRange = ref(null)

const filterOptions = reactive({ modules: [], actions: [] })

const moduleMap = {
  product: '商品管理', stock_in: '入库管理', stock_out: '出库管理',
  inventory: '盘点管理', user: '用户管理', category: '分类管理',
}
const actionMap = {
  create: '创建', update: '更新', delete: '删除',
  reverse: '冲销', confirm: '确认', login: '登录',
}

onMounted(() => {
  fetchList()
  fetchOptions()
})

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      page_size: pageSize.value,
      keyword: keyword.value || undefined,
      module: filterModule.value || undefined,
      action: filterAction.value || undefined,
    }
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    const res = await getLogListApi(params)
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

async function fetchOptions() {
  try {
    const res = await getLogOptionsApi()
    filterOptions.modules = res.data.modules || []
    filterOptions.actions = res.data.actions || []
  } catch { /* ignore */ }
}
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0;
  background: #fff;
  padding: 15px;
  border-radius: 4px;
}
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 15px;
  background: #fff;
  padding: 15px;
  border-radius: 4px;
}
</style>
