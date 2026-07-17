<template>
  <div>
    <!-- 查询条件 -->
    <div class="toolbar">
      <el-input v-model="keyword" placeholder="搜索商品名称/编码" style="width: 240px" clearable
        @clear="fetchList" @keyup.enter="fetchList" />
      <el-select v-model="categoryId" placeholder="按分类筛选" clearable
        style="width: 180px; margin-left: 12px" @change="fetchList">
        <el-option v-for="c in categoryOptions" :key="c.id" :label="c.name" :value="c.id" />
      </el-select>
      <el-switch v-model="alertOnly" active-text="仅看预警" style="margin-left: 16px"
        @change="fetchList" />
      <el-button style="margin-left: auto" type="primary" @click="fetchList">查询</el-button>
    </div>

    <!-- 库存表格 -->
    <el-table :data="list" stripe border highlight-current-row style="margin-top: 15px"
      v-loading="loading" :row-class-name="tableRowClassName"
      @row-click="showFlow">
      <el-table-column prop="code" label="商品编码" width="140" />
      <el-table-column prop="name" label="商品名称" min-width="150" />
      <el-table-column prop="category.name" label="分类" width="120" />
      <el-table-column prop="spec" label="规格" width="100" />
      <el-table-column prop="unit" label="单位" width="70" />
      <el-table-column prop="current_stock" label="当前库存" width="100" sortable />
      <el-table-column prop="safety_stock" label="安全库存" width="100" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag v-if="row.current_stock < row.safety_stock"
            type="danger" size="small">库存预警</el-tag>
          <el-tag v-else-if="row.current_stock === 0"
            type="warning" size="small">已售罄</el-tag>
          <el-tag v-else type="success" size="small">正常</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="80" fixed="right">
        <template #default="{ row }">
          <el-button size="small" text type="primary" @click.stop="showFlow(row)">流水</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div class="pagination-wrap">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize"
        :page-sizes="[10, 15, 20, 50]" :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList" @current-change="fetchList" />
    </div>

    <!-- 流水明细弹窗 -->
    <el-dialog v-model="flowVisible" :title="`出入库流水 - ${flowProductName}`"
      width="950px" @closed="flowList = []; flowTotal = 0;">
      <div class="toolbar" style="margin-bottom: 12px;">
        <el-date-picker v-model="flowDateRange" type="daterange"
          range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"
          value-format="YYYY-MM-DD" style="width: 260px" @change="fetchFlow" />
        <el-button style="margin-left: auto" @click="flowVisible = false">关闭</el-button>
      </div>
      <el-table :data="flowList" stripe border size="small" v-loading="flowLoading">
        <el-table-column prop="type" label="类型" width="70">
          <template #default="{ row }">
            <el-tag :type="row.type === '入库' ? 'success' : 'danger'" size="small">
              {{ row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="order_no" label="单号" width="185" />
        <el-table-column prop="code" label="编码" width="130" />
        <el-table-column label="数量" width="80">
          <template #default="{ row }">
            <span :style="{ color: row.quantity > 0 ? '#67c23a' : '#f56c6c', fontWeight: 'bold' }">
              {{ row.quantity > 0 ? '+' + row.quantity : row.quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="unit_price" label="单价" width="100" />
        <el-table-column label="金额" width="100">
          <template #default="{ row }">
            <span :style="{ color: row.subtotal > 0 ? '#67c23a' : '#f56c6c' }">
              {{ row.subtotal }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="170" />
      </el-table>
      <div class="pagination-wrap">
        <el-pagination v-model:current-page="flowPage" v-model:page-size="flowPageSize"
          :total="flowTotal" layout="total, prev, pager, next" size="small"
          @current-change="fetchFlow" @size-change="fetchFlow" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getStockQueryApi, getStockFlowApi } from '@/api/stockQuery'
import { getCategoryListApi } from '@/api/category'

// ===== 库存查询 =====
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)
const keyword = ref('')
const categoryId = ref(null)
const alertOnly = ref(false)
const categoryOptions = ref([])

onMounted(() => {
  fetchList()
  fetchCategories()
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getStockQueryApi({
      page: page.value,
      page_size: pageSize.value,
      keyword: keyword.value || undefined,
      category_id: categoryId.value || undefined,
      alert_only: alertOnly.value || undefined,
    })
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const res = await getCategoryListApi()
    categoryOptions.value = res.data
  } catch { /* 忽略 */ }
}

/** 预警行红色高亮 */
function tableRowClassName({ row }) {
  if (row.current_stock < row.safety_stock) return 'warning-row'
  return ''
}

// ===== 流水明细 =====
const flowVisible = ref(false)
const flowLoading = ref(false)
const flowList = ref([])
const flowTotal = ref(0)
const flowPage = ref(1)
const flowPageSize = ref(20)
const flowProductId = ref(null)
const flowProductName = ref('')
const flowDateRange = ref(null)

async function showFlow(row) {
  flowProductId.value = row.id
  flowProductName.value = row.name
  flowPage.value = 1
  flowDateRange.value = null
  flowVisible.value = true
  await fetchFlow()
}

async function fetchFlow() {
  flowLoading.value = true
  try {
    const params = {
      product_id: flowProductId.value,
      page: flowPage.value,
      page_size: flowPageSize.value,
    }
    if (flowDateRange.value && flowDateRange.value.length === 2) {
      params.start_date = flowDateRange.value[0]
      params.end_date = flowDateRange.value[1]
    }
    const res = await getStockFlowApi(params)
    flowList.value = res.data.data
    flowTotal.value = res.data.total
  } finally {
    flowLoading.value = false
  }
}
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
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
:deep(.warning-row) {
  background-color: #fef0f0 !important;
}
:deep(.warning-row:hover > td) {
  background-color: #fde2e2 !important;
}
</style>
