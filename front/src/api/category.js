import request from './request'

// 分类列表（树形）
export function getCategoryListApi(params) {
  return request.get('/categories', { params })
}

// 新增分类
export function createCategoryApi(data) {
  return request.post('/categories', data)
}

// 编辑分类
export function updateCategoryApi(id, data) {
  return request.put(`/categories/${id}`, data)
}

// 删除分类
export function deleteCategoryApi(id) {
  return request.delete(`/categories/${id}`)
}
