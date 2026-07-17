import request from './request'

/**
 * 操作日志列表
 */
export function getLogListApi(params) {
  return request.get('/logs', { params })
}

/**
 * 操作日志筛选选项
 */
export function getLogOptionsApi() {
  return request.get('/logs/options')
}
