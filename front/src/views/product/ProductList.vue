<template>
  <div>
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <el-input v-model="keyword" placeholder="搜索商品名称/编码" style="width: 220px" clearable
        @clear="fetchList" @keyup.enter="fetchList" />
      <el-tree-select v-model="categoryFilter" :data="categoryOptions" :props="{ label: 'name', value: 'id' }"
        placeholder="选择分类" style="width: 200px; margin-left: 10px" clearable check-strictly
        @change="fetchList" />
      <el-button v-if="canWrite" type="primary" style="margin-left: auto" @click="openDialog()">新增商品</el-button>
      <el-button v-if="canWrite" style="margin-left: 10px" @click="triggerImport">批量导入</el-button>
      <input ref="fileInputRef" type="file" accept=".xlsx,.csv,.xls" style="display: none"
        @change="handleFileChange" />
    </div>

    <!-- 商品表格 -->
    <el-table :data="list" stripe border style="margin-top: 15px" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="code" label="商品编码" width="130" />
      <el-table-column prop="name" label="商品名称" min-width="150" />
      <el-table-column label="分类" width="120">
        <template #default="{ row }">{{ row.category?.name || '-' }}</template>
      </el-table-column>
      <el-table-column prop="spec" label="规格" width="100" />
      <el-table-column prop="unit" label="单位" width="70" />
      <el-table-column label="当前库存" width="100">
        <template #default="{ row }">
          <span :style="{ color: row.current_stock < row.safety_stock ? '#f56c6c' : '', fontWeight: row.current_stock < row.safety_stock ? 'bold' : '' }">
            {{ row.current_stock }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="safety_stock" label="安全库存" width="90" />
      <el-table-column prop="purchase_price" label="参考进价" width="100" />
      <el-table-column prop="sale_price" label="参考售价" width="100" />
      <el-table-column v-if="canWrite" label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-popconfirm title="确定要删除此商品吗？" @confirm="handleDelete(row.id)">
            <template #reference>
              <el-button size="small" type="danger">删除</el-button>
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

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="visible" :title="isEdit ? '编辑商品' : '新增商品'" width="600px" @closed="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="商品编码" prop="code">
          <el-input v-model="form.code" placeholder="请输入商品编码/条码" :disabled="isEdit" />
        </el-form-item>
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入商品名称" />
        </el-form-item>
        <el-form-item label="分类" prop="category_id">
          <el-tree-select v-model="form.category_id" :data="categoryOptions"
            :props="{ label: 'name', value: 'id' }" placeholder="请选择分类"
            style="width: 100%" clearable check-strictly />
        </el-form-item>
        <el-form-item label="规格型号" prop="spec">
          <el-input v-model="form.spec" placeholder="请输入规格型号" />
        </el-form-item>
        <el-form-item label="单位" prop="unit">
          <el-input v-model="form.unit" placeholder="如：件、个、kg" />
        </el-form-item>
        <el-form-item label="参考进价" prop="purchase_price">
          <el-input-number v-model="form.purchase_price" :precision="2" :min="0" style="width: 100%"
            controls-position="right" />
        </el-form-item>
        <el-form-item label="参考售价" prop="sale_price">
          <el-input-number v-model="form.sale_price" :precision="2" :min="0" style="width: 100%"
            controls-position="right" />
        </el-form-item>
        <el-form-item label="安全库存" prop="safety_stock">
          <el-input-number v-model="form.safety_stock" :min="0" style="width: 100%" controls-position="right" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="可选备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getProductListApi, createProductApi, updateProductApi, deleteProductApi, importProductApi, getCategoryOptionsApi } from '@/api/product'
import { useRole } from '@/composables/useRole'

const { canWrite } = useRole()

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)
const keyword = ref('')
const categoryFilter = ref(null)

const categoryOptions = ref([])

const visible = ref(false)
const isEdit = ref(false)
const submitting = ref(false)
const formRef = ref(null)
const fileInputRef = ref(null)
const form = reactive({
  code: '',
  name: '',
  category_id: null,
  spec: '',
  unit: '件',
  purchase_price: 0,
  sale_price: 0,
  safety_stock: 0,
  remark: ''
})

const rules = {
  code: [{ required: true, message: '请输入商品编码', trigger: 'blur' }],
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  unit: [{ required: true, message: '请输入单位', trigger: 'blur' }]
}

onMounted(() => {
  fetchList()
  fetchCategories()
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getProductListApi({
      page: page.value,
      page_size: pageSize.value,
      keyword: keyword.value,
      category_id: categoryFilter.value || undefined
    })
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const res = await getCategoryOptionsApi()
    categoryOptions.value = res.data
  } catch { /* 忽略 */ }
}

function openDialog(row) {
  if (row) {
    isEdit.value = true
    Object.assign(form, {
      id: row.id,
      code: row.code,
      name: row.name,
      category_id: row.category_id,
      spec: row.spec || '',
      unit: row.unit,
      purchase_price: row.purchase_price,
      sale_price: row.sale_price,
      safety_stock: row.safety_stock,
      remark: row.remark || ''
    })
  } else {
    isEdit.value = false
  }
  visible.value = true
}

function resetForm() {
  formRef.value?.resetFields()
  Object.assign(form, {
    code: '', name: '', category_id: null, spec: '', unit: '件',
    purchase_price: 0, sale_price: 0, safety_stock: 0, remark: ''
  })
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    const payload = { ...form }
    if (!payload.category_id) payload.category_id = null

    if (isEdit.value) {
      await updateProductApi(form.id, payload)
      ElMessage.success('商品更新成功')
    } else {
      await createProductApi(payload)
      ElMessage.success('商品创建成功')
    }
    visible.value = false
    fetchList()
    fetchCategories() // 刷新分类选项
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id) {
  try {
    await deleteProductApi(id)
    ElMessage.success('商品已删除')
    fetchList()
  } catch { /* 已统一处理 */ }
}

function triggerImport() {
  fileInputRef.value?.click()
}

async function handleFileChange(e) {
  const file = e.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)

  try {
    await importProductApi(formData)
    ElMessage.success('导入成功')
    fetchList()
  } catch { /* 已统一处理 */ }
  finally {
    if (fileInputRef.value) fileInputRef.value.value = ''
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
</style>
