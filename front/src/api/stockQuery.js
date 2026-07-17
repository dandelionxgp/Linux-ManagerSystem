import request from './request'

/**
 * 实时库存查询（搜索/分类/预警）
 */
export function getStockQueryApi(params) {
  return request.get('/stock/query', { params })
}

/**
 * 库存预警列表
 */
export function getStockAlertsApi(params) {
  return request.get('/stock/alerts', { params })
}

/**
 * 出入库流水明细
 */
export function getStockFlowApi(params) {
  return request.get('/stock/flow', { params })
}
