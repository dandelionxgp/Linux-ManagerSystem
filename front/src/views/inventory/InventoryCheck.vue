<template>
  <div>
    <!-- 工具栏 -->
    <div class="toolbar">
      <el-select v-model="filterStatus" placeholder="按状态筛选" clearable
        style="width: 150px" @change="fetchList">
        <el-option label="新建" :value="1" />
        <el-option label="已录入" :value="2" />
        <el-option label="已确认" :value="3" />
      </el-select>
      <el-button type="primary" style="margin-left: auto" @click="openCreateDialog">
        <el-icon style="margin-right: 4px;"><Plus /></el-icon>新建盘点
      </el-button>
    </div>

    <!-- 盘点单列表 -->
    <el-table :data="list" stripe border style="margin-top: 15px"
      v-loading="loading" highlight-current-row @row-click="openDetail">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="order_no" label="盘点单号" width="185" />
      <el-table-column label="盘点范围" width="120">
        <template #default="{ row }">
          {{ row.category_id ? '按分类' : '全部商品' }}
        </template>
      </el-table-column>
      <el-table-column prop="items_count" label="商品数" width="80" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag v-if="row.status === 1" size="small">新建</el-tag>
          <el-tag v-else-if="row.status === 2" type="warning" size="small">已录入</el-tag>
          <el-tag v-else type="success" size="small">已确认</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="operator" label="经办人" width="100" />
      <el-table-column prop="created_at" label="创建时间" width="170" />
    </el-table>

    <div class="pagination-wrap">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize"
        :page-sizes="[10, 15, 20]" :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList" @current-change="fetchList" />
    </div>

    <!-- 新建盘点弹窗 -->
    <el-dialog v-model="createVisible" title="新建盘点单" width="500px"
      @closed="resetCreateForm">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules"
        label-width="80px">
        <el-form-item label="盘点范围">
          <el-radio-group v-model="createForm.rangeType">
            <el-radio value="all">全部商品</el-radio>
            <el-radio value="category">按分类</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="createForm.rangeType === 'category'" label="选择分类"
          prop="category_id">
          <el-select v-model="createForm.category_id" placeholder="请选择分类" style="width: 100%">
            <el-option v-for="c in categoryOptions" :key="c.id"
              :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="经办人" prop="operator">
          <el-input v-model="createForm.operator" placeholder="请输入经办人" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.remark" placeholder="可选备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>

    <!-- 盘点详情弹窗 -->
    <el-dialog v-model="detailVisible"
      :title="`盘点详情 - ${detail?.order_no || ''}`" width="980px"
      @closed="detail = null">
      <!-- 基本信息 -->
      <el-descriptions :column="3" border v-if="detail" size="small">
        <el-descriptions-item label="盘点单号">{{ detail.order_no }}</el-descriptions-item>
        <el-descriptions-item label="经办人">{{ detail.operator }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag v-if="detail.status === 1" size="small">新建</el-tag>
          <el-tag v-else-if="detail.status === 2" type="warning" size="small">已录入</el-tag>
          <el-tag v-else type="success" size="small">已确认</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="备注">{{ detail.remark || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
        <el-descriptions-item v-if="detail.confirmed_at" label="确认时间">
          {{ detail.confirmed_at }}
        </el-descriptions-item>
      </el-descriptions>

      <!-- 明细表格 -->
      <el-table :data="detail?.items || []" border stripe size="small"
        style="margin-top: 15px" max-height="400">
        <el-table-column prop="product.code" label="编码" width="130" />
        <el-table-column prop="product.name" label="商品名称" min-width="150" />
        <el-table-column prop="product.unit" label="单位" width="65" />
        <el-table-column prop="product.spec" label="规格" width="100" />
        <el-table-column prop="system_qty" label="系统库存" width="90" />
        <el-table-column label="实盘数量" width="140">
          <template #default="{ row }">
            <el-input-number v-if="detail?.status < 3"
              v-model="row.actual_qty" :min="0" size="small"
              controls-position="right" style="width: 120px"
              :placeholder="row.actual_qty === null ? '待录入' : ''" />
            <span v-else>{{ row.actual_qty ?? '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="差异" width="100">
          <template #default="{ row }">
            <span v-if="row.actual_qty !== null && row.actual_qty !== undefined"
              :style="{
                color: (row.actual_qty - row.system_qty) > 0 ? '#67c23a'
                  : (row.actual_qty - row.system_qty) < 0 ? '#f56c6c' : '#909399',
                fontWeight: 'bold'
              }">
              {{ (row.actual_qty - row.system_qty) > 0 ? '+' : ''
              }}{{ row.actual_qty - row.system_qty }}
            </span>
            <span v-else style="color: #c0c4cc;">-</span>
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <div style="display: flex; justify-content: space-between;">
          <el-button v-if="detail" type="success" @click="handlePrint"
            :disabled="!detail">
            <el-icon style="margin-right: 4px;"><Printer /></el-icon>打印报告
          </el-button>
          <div>
            <el-button @click="detailVisible = false">关闭</el-button>
            <el-button v-if="detail?.status < 3" type="primary"
              @click="handleSaveItems" :loading="saving">
              {{ detail?.status === 1 ? '保存实盘数据' : '更新实盘数据' }}
            </el-button>
            <el-popconfirm v-if="detail?.status === 2"
              title="确认盘点后将自动生成出入库单据调整库存，确定继续？"
              confirm-button-text="确认盘点" cancel-button-text="取消"
              @confirm="handleConfirm">
              <template #reference>
                <el-button type="success" :loading="confirming">确认盘点</el-button>
              </template>
            </el-popconfirm>
          </div>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Printer } from '@element-plus/icons-vue'
import {
  getInventoryListApi, getInventoryDetailApi, createInventoryApi,
  updateInventoryItemsApi, confirmInventoryApi
} from '@/api/inventory'
import { getInventoryPrintApi } from '@/api/report'
import { getCategoryListApi } from '@/api/category'
import { usePrint } from '@/composables/usePrint'

// ===== 列表 =====
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)
const filterStatus = ref(null)

onMounted(() => fetchList())

async function fetchList() {
  loading.value = true
  try {
    const res = await getInventoryListApi({
      page: page.value,
      page_size: pageSize.value,
      status: filterStatus.value || undefined,
    })
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

// ===== 新建盘点 =====
const createVisible = ref(false)
const creating = ref(false)
const createFormRef = ref(null)
const categoryOptions = ref([])
const createForm = reactive({
  rangeType: 'all',
  category_id: null,
  operator: '',
  remark: '',
})
const createRules = {
  operator: [{ required: true, message: '请输入经办人', trigger: 'blur' }],
}

async function openCreateDialog() {
  try {
    const res = await getCategoryListApi()
    categoryOptions.value = res.data || []
  } catch { categoryOptions.value = [] }
  createVisible.value = true
}

function resetCreateForm() {
  createFormRef.value?.resetFields()
  createForm.rangeType = 'all'
  createForm.category_id = null
  createForm.operator = ''
  createForm.remark = ''
}

async function handleCreate() {
  const valid = await createFormRef.value.validate().catch(() => false)
  if (!valid) return

  creating.value = true
  try {
    const payload = {
      operator: createForm.operator,
      remark: createForm.remark || undefined,
      category_id: createForm.rangeType === 'category'
        ? createForm.category_id : null,
    }
    await createInventoryApi(payload)
    ElMessage.success('盘点单已创建')
    createVisible.value = false
    fetchList()
  } finally {
    creating.value = false
  }
}

// ===== 详情 & 录入 =====
const detailVisible = ref(false)
const detail = ref(null)
const saving = ref(false)
const confirming = ref(false)

async function openDetail(row) {
  try {
    const res = await getInventoryDetailApi(row.id)
    detail.value = res.data
    detailVisible.value = true
  } catch { /* ignore */ }
}

async function handleSaveItems() {
  const items = detail.value.items.map(i => ({
    product_id: i.product_id,
    actual_qty: i.actual_qty,
  }))

  // 检查是否全部填写
  const unfilled = items.find(i => i.actual_qty === null || i.actual_qty === undefined)
  if (unfilled !== undefined) {
    ElMessage.warning('请填写所有商品的实盘数量')
    return
  }

  saving.value = true
  try {
    await updateInventoryItemsApi(detail.value.id, { items })
    ElMessage.success('实盘数据已保存')
    // 刷新详情
    const res = await getInventoryDetailApi(detail.value.id)
    detail.value = res.data
    fetchList()
  } finally {
    saving.value = false
  }
}

async function handleConfirm() {
  confirming.value = true
  try {
    await confirmInventoryApi(detail.value.id)
    ElMessage.success('盘点已确认，库存已自动调整')
    const res = await getInventoryDetailApi(detail.value.id)
    detail.value = res.data
    fetchList()
  } finally {
    confirming.value = false
  }
}

async function handlePrint() {
  if (!detail.value) return
  try {
    const res = await getInventoryPrintApi(detail.value.id)
    usePrint(res.data)
  } catch { /* ignore */ }
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
</style>
