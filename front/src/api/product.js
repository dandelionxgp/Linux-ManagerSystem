import request from './request'

/**
 * 商品列表（分页 + 筛选）
 */
export function getProductListApi(params) {
  return request.get('/products', { params })
}

/**
 * 新增商品
 */
export function createProductApi(data) {
  return request.post('/products', data)
}

/**
 * 编辑商品
 */
export function updateProductApi(id, data) {
  return request.put(`/products/${id}`, data)
}

/**
 * 删除商品
 */
export function deleteProductApi(id) {
  return request.delete(`/products/${id}`)
}

/**
 * 批量导入商品
 */
export function importProductApi(formData) {
  return request.post('/products/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

/**
 * 获取分类选项（供商品表单下拉选择用）
 */
export function getCategoryOptionsApi() {
  return request.get('/categories')
}
