import request from './request'

// ========== 入库 ==========

/**
 * 入库单列表
 */
export function getStockInListApi(params) {
  return request.get('/stock-ins', { params })
}

/**
 * 入库单详情
 */
export function getStockInDetailApi(id) {
  return request.get(`/stock-ins/${id}`)
}

/**
 * 创建入库单
 */
export function createStockInApi(data) {
  return request.post('/stock-ins', data)
}

/**
 * 冲销入库单
 */
export function reverseStockInApi(id) {
  return request.post(`/stock-ins/${id}/reverse`)
}

// ========== 出库 ==========

/**
 * 出库单列表
 */
export function getStockOutListApi(params) {
  return request.get('/stock-outs', { params })
}

/**
 * 出库单详情
 */
export function getStockOutDetailApi(id) {
  return request.get(`/stock-outs/${id}`)
}

/**
 * 创建出库单
 */
export function createStockOutApi(data) {
  return request.post('/stock-outs', data)
}

/**
 * 冲销出库单
 */
export function reverseStockOutApi(id) {
  return request.post(`/stock-outs/${id}/reverse`)
}
