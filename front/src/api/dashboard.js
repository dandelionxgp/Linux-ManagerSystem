import request from './request'

/**
 * 仪表盘聚合数据
 */
export function getDashboardApi() {
  return request.get('/dashboard')
}
