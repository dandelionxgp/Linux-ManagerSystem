import request from './request'

/**
 * 入库单打印数据
 */
export function getStockInPrintApi(id) {
  return request.get(`/reports/stock-in/${id}/print`)
}

/**
 * 出库单打印数据
 */
export function getStockOutPrintApi(id) {
  return request.get(`/reports/stock-out/${id}/print`)
}

/**
 * 盘点报告打印数据
 */
export function getInventoryPrintApi(id) {
  return request.get(`/reports/inventory/${id}/print`)
}

/**
 * 库存汇总报表
 */
export function getStockSummaryApi(params) {
  return request.get('/reports/stock-summary', { params })
}
