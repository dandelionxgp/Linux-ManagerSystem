<template>
  <div>
    <!-- 顶部工具栏 -->
    <div class="toolbar">
      <el-input v-model="keyword" placeholder="搜索用户名/姓名" style="width: 220px" clearable
        @clear="fetchList" @keyup.enter="fetchList" />
      <el-select v-model="roleFilter" placeholder="角色筛选" style="width: 150px; margin-left: 10px" clearable
        @change="fetchList">
        <el-option label="管理员" value="admin" />
        <el-option label="经理" value="manager" />
        <el-option label="观察者" value="viewer" />
      </el-select>
      <el-button type="primary" style="margin-left: auto" @click="openDialog()">新增用户</el-button>
    </div>

    <!-- 用户表格 -->
    <el-table :data="list" stripe border style="margin-top: 15px" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="username" label="用户名" width="120" />
      <el-table-column prop="real_name" label="姓名" width="120" />
      <el-table-column prop="role" label="角色" width="100">
        <template #default="{ row }">
          <el-tag :type="row.role === 'admin' ? 'danger' : row.role === 'manager' ? 'warning' : 'info'" size="small">
            {{ row.role === 'admin' ? '管理员' : row.role === 'manager' ? '经理' : '观察者' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status ? 'success' : 'danger'" size="small">
            {{ row.status ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login_at" label="最后登录" width="170" />
      <el-table-column label="操作" width="200">
        <template #default="{ row }">
          <el-button size="small" @click="openDialog(row)">编辑</el-button>
          <el-popconfirm title="确定要删除此用户吗？" @confirm="handleDelete(row.id)">
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
    <el-dialog v-model="visible" :title="isEdit ? '编辑用户' : '新增用户'" width="500px" @closed="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="form.username" :disabled="isEdit" placeholder="请输入用户名" />
        </el-form-item>
        <el-form-item label="姓名" prop="real_name">
          <el-input v-model="form.real_name" placeholder="请输入真实姓名" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="form.password" type="password"
            :placeholder="isEdit ? '不修改请留空' : '请输入密码'" show-password />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-select v-model="form.role" style="width: 100%">
            <el-option label="管理员" value="admin" />
            <el-option label="经理" value="manager" />
            <el-option label="观察者" value="viewer" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status" v-if="isEdit">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
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
import { getUserListApi, createUserApi, updateUserApi, deleteUserApi } from '@/api/user'

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)
const loading = ref(false)
const keyword = ref('')
const roleFilter = ref('')

const visible = ref(false)
const isEdit = ref(false)
const submitting = ref(false)
const formRef = ref(null)
const form = reactive({
  username: '',
  real_name: '',
  password: '',
  role: 'viewer',
  status: 1
})

const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  real_name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  password: isEdit.value
    ? []
    : [{ required: true, message: '请输入密码', trigger: 'blur' }, { min: 6, message: '密码至少6位', trigger: 'blur' }],
  role: [{ required: true, message: '请选择角色', trigger: 'change' }]
}

onMounted(() => fetchList())

async function fetchList() {
  loading.value = true
  try {
    const res = await getUserListApi({ page: page.value, page_size: pageSize.value, keyword: keyword.value, role: roleFilter.value })
    list.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

function openDialog(row) {
  if (row) {
    isEdit.value = true
    Object.assign(form, {
      id: row.id,
      username: row.username,
      real_name: row.real_name,
      password: '',
      role: row.role,
      status: row.status
    })
  } else {
    isEdit.value = false
  }
  visible.value = true
}

function resetForm() {
  formRef.value?.resetFields()
  Object.assign(form, { username: '', real_name: '', password: '', role: 'viewer', status: 1 })
}

async function handleSubmit() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    if (isEdit.value) {
      const payload = { real_name: form.real_name, role: form.role, status: form.status }
      if (form.password) payload.password = form.password
      await updateUserApi(form.id, payload)
      ElMessage.success('用户更新成功')
    } else {
      await createUserApi(form)
      ElMessage.success('用户创建成功')
    }
    visible.value = false
    fetchList()
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id) {
  try {
    await deleteUserApi(id)
    ElMessage.success('用户已删除')
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
