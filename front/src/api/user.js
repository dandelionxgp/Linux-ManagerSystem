import request from './request'

// 用户列表
export function getUserListApi(params) {
  return request.get('/users', { params })
}

// 新增用户
export function createUserApi(data) {
  return request.post('/users', data)
}

// 编辑用户
export function updateUserApi(id, data) {
  return request.put(`/users/${id}`, data)
}

// 删除用户
export function deleteUserApi(id) {
  return request.delete(`/users/${id}`)
}
