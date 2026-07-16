import request from './request'

// 登录
export function loginApi(data) {
  return request.post('/auth/login', data)
}

// 登出
export function logoutApi() {
  return request.post('/auth/logout')
}

// 获取当前用户信息
export function getCurrentUserApi() {
  return request.get('/auth/me')
}
