import request from './request'

/**
 * 盘点单列表
 */
export function getInventoryListApi(params) {
  return request.get('/inventories', { params })
}

/**
 * 盘点单详情
 */
export function getInventoryDetailApi(id) {
  return request.get(`/inventories/${id}`)
}

/**
 * 创建盘点单
 */
export function createInventoryApi(data) {
  return request.post('/inventories', data)
}

/**
 * 录入实盘数量
 */
export function updateInventoryItemsApi(id, data) {
  return request.put(`/inventories/${id}/items`, data)
}

/**
 * 确认盘点
 */
export function confirmInventoryApi(id) {
  return request.post(`/inventories/${id}/confirm`)
}
