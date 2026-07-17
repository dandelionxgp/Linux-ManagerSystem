<template>
  <div>
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <el-input v-model="keyword" placeholder="搜索单号/供应商" style="width: 220px" clearable
        @clear="fetchList" @keyup.enter="fetchList" />
      <el-button type="primary" style="margin-left: auto" @click="openCreateDialog">新增入库单</el-button>
    </div>

    <!-- 入库单表格 -->
    <el-table :data="list" stripe border style="margin-top: 15px" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="order_no" label="入库单号" width="180" />
      <el-table-column prop="supplier" label="供应商" width="150" />
      <el-table-column prop="total_amount" label="总金额" width="120" />
      <el-table-column prop="operator" label="经办人" width="100" />
      <el-table-column label="商品数" width="80">
        <template #default="{ row }">{{ row.items?.length || 0 }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
            {{ row.status === 1 ? '正常' : '已冲销' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="170" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">详情</el-button>
          <el-popconfirm v-if="row.status === 1" title="确定要冲销此入库单吗？库存将减少。"
            @confirm="handleReverse(row.id)">
            <template #reference>
              <el-button size="small" type="warning">冲销</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div class="pagination-wrap">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize"
        :page-sizes="[10, 15, 20, 50]" :total="total" layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList" @current-change="fetchList" />
    </div>

    <!-- 新增入库单弹窗 -->
    <el-dialog v-model="createVisible" title="新增入库单" width="850px" @closed="resetCreateForm">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="80px">
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="供应商" prop="supplier">
              <el-input v-model="createForm.supplier" placeholder="请输入供应商名称" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="经办人" prop="operator">
              <el-input v-model="createForm.operator" placeholder="请输入经办人" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="备注">
              <el-input v-model="createForm.remark" placeholder="可选备注" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">入库明细</el-divider>

        <el-table :data="createForm.items" border stripe size="small">
          <el-table-column label="商品" min-width="200">
            <template #default="{ row }">
              <el-select v-model="row.product_id" filterable placeholder="请选择商品" style="width: 100%"
                @change="(val) => onProductSelect(val, row)">
                <el-option v-for="p in productOptions" :key="p.id" :label="`${p.code} - ${p.name}`" :value="p.id" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="数量" width="130">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="1" style="width: 110px" controls-position="right" />
            </template>
          </el-table-column>
          <el-table-column label="单价" width="130">
            <template #default="{ row }">
              <el-input-number v-model="row.unit_price" :min="0" :precision="2" style="width: 110px" controls-position="right" />
            </template>
          </el-table-column>
          <el-table-column label="小计" width="110">
            <template #default="{ row }">
              {{ (row.quantity * row.unit_price).toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="70">
            <template #default="{ $index }">
              <el-button size="small" type="danger" text @click="removeItem($index)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-button type="success" plain size="small" style="margin-top: 10px" @click="addItem">
          + 添加商品
        </el-button>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">提交入库</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="入库单详情" width="750px">
      <el-descriptions :column="3" border v-if="detail">
        <el-descriptions-item label="入库单号">{{ detail.order_no }}</el-descriptions-item>
        <el-descriptions-item label="供应商">{{ detail.supplier || '-' }}</el-descriptions-item>
        <el-descriptions-item label="经办人">{{ detail.operator }}</el-descriptions-item>
        <el-descriptions-item label="总金额">{{ detail.total_amount }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="detail.status === 1 ? 'success' : 'info'" size="small">
            {{ detail.status === 1 ? '正常' : '已冲销' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
      </el-descriptions>
      <el-table :data="detail?.items || []" border stripe size="small" style="margin-top: 15px">
        <el-table-column prop="product.code" label="编码" width="130" />
        <el-table-column prop="product.name" label="商品名称" min-width="150" />
        <el-table-column prop="quantity" label="数量" width="80" />
        <el-table-column prop="unit_price" label="单价" width="100" />
        <el-table-column prop="subtotal" label="小计" width="110" />
      </el-table>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getStockInListApi, getStockInDetailApi, createStockInApi, reverseStockInApi } from '@/api/stock'
import { getProductListApi } from '@/api/product'

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)
const keyword = ref('')

const creating = ref(false)
const createVisible = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  supplier: '',
  operator: '',
  remark: '',
  items: []
})

const createRules = {
  operator: [{ required: true, message: '请输入经办人', trigger: 'blur' }]
}

const productOptions = ref([])

const detailVisible = ref(false)
const detail = ref(null)

onMounted(() => fetchList())

async function fetchList() {
  loading.value = true
  try {
    const res = await getStockInListApi({
      page: page.value,
      page_size: pageSize.value,
      keyword: keyword.value
    })
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

async function fetchProductOptions() {
  try {
    const res = await getProductListApi({ page_size: 999 })
    productOptions.value = res.data.data
  } catch { /* 忽略 */ }
}

function openCreateDialog() {
  fetchProductOptions()
  createVisible.value = true
}

function addItem() {
  createForm.items.push({ product_id: null, quantity: 1, unit_price: 0 })
}

function removeItem(index) {
  createForm.items.splice(index, 1)
}

function onProductSelect(productId, row) {
  const product = productOptions.value.find(p => p.id === productId)
  if (product) {
    row.unit_price = product.purchase_price || 0
  }
}

function resetCreateForm() {
  createFormRef.value?.resetFields()
  createForm.supplier = ''
  createForm.operator = ''
  createForm.remark = ''
  createForm.items = []
}

async function handleCreate() {
  if (createForm.items.length === 0) {
    ElMessage.warning('请至少添加一个商品')
    return
  }

  // 校验所有商品已选择
  if (createForm.items.some(item => !item.product_id)) {
    ElMessage.warning('请为每行选择商品')
    return
  }

  const valid = await createFormRef.value.validate().catch(() => false)
  if (!valid) return

  creating.value = true
  try {
    await createStockInApi({ ...createForm })
    ElMessage.success('入库成功')
    createVisible.value = false
    fetchList()
  } finally {
    creating.value = false
  }
}

async function showDetail(row) {
  try {
    const res = await getStockInDetailApi(row.id)
    detail.value = res.data
    detailVisible.value = true
  } catch { /* 已统一处理 */ }
}

async function handleReverse(id) {
  try {
    await reverseStockInApi(id)
    ElMessage.success('入库单已冲销')
    fetchList()
  } catch { /* 已统一处理 */ }
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
