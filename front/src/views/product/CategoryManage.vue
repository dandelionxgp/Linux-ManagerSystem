<template>
  <div>
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <el-button type="primary" @click="openDialog()">新增分类</el-button>
    </div>

    <!-- 分类表格 -->
    <el-table :data="list" stripe border style="margin-top: 15px" v-loading="loading"
      row-key="id" default-expand-all>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="分类名称" />
      <el-table-column prop="sort_order" label="排序" width="80" />
      <el-table-column prop="created_at" label="创建时间" width="170" />
      <el-table-column label="操作" width="200">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <!-- 新增子分类 -->
          <el-button size="small" type="success" @click="openDialog(null, row.id)">
            添加子分类
          </el-button>
          <el-popconfirm title="确定要删除此分类吗？" @confirm="handleDelete(row.id)">
            <template #reference>
              <el-button size="small" type="danger">删除</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="visible" :title="dialogTitle" width="500px" @closed="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="父分类">
          <el-tree-select v-model="form.parent_id" :data="parentOptions"
            :props="{ label: 'name', value: 'id', children: 'children' }"
            placeholder="不选则为顶级分类" clearable check-strictly
            style="width: 100%" />
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入分类名称" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :max="999" />
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
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getCategoryListApi, createCategoryApi, updateCategoryApi, deleteCategoryApi } from '@/api/category'

const list = ref([])
const loading = ref(false)

const visible = ref(false)
const isEdit = ref(false)
const submitting = ref(false)
const formRef = ref(null)
const form = reactive({ parent_id: null, name: '', sort_order: 0 })
const editId = ref(null)
const rules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }]
}

// 父分类选项（排除自身及其子分类，避免循环引用）
const parentOptions = computed(() => {
  if (!isEdit.value) return list.value
  // 简单过滤：排除自身
  function filterSelf(tree, id) {
    return tree.filter(item => {
      if (item.id === id) return false
      if (item.children) item.children = filterSelf(item.children, id)
      return true
    })
  }
  return filterSelf(JSON.parse(JSON.stringify(list.value)), editId.value)
})

const dialogTitle = computed(() => {
  if (isEdit.value) return '编辑分类'
  if (form.parent_id) return '添加子分类'
  return '新增分类'
})

onMounted(() => fetchList())

async function fetchList() {
  loading.value = true
  try {
    const res = await getCategoryListApi()
    list.value = res.data
  } finally {
    loading.value = false
  }
}

function openDialog(row, parentId) {
  if (row) {
    isEdit.value = true
    editId.value = row.id
    Object.assign(form, { parent_id: row.parent_id, name: row.name, sort_order: row.sort_order })
  } else {
    isEdit.value = false
    editId.value = null
    form.parent_id = parentId || null
  }
  visible.value = true
}

function resetForm() {
  formRef.value?.resetFields()
  Object.assign(form, { parent_id: null, name: '', sort_order: 0 })
  editId.value = null
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    if (isEdit.value) {
      await updateCategoryApi(editId.value, form)
      ElMessage.success('分类更新成功')
    } else {
      await createCategoryApi(form)
      ElMessage.success('分类创建成功')
    }
    visible.value = false
    fetchList()
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id) {
  try {
    await deleteCategoryApi(id)
    ElMessage.success('分类已删除')
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
</style>
